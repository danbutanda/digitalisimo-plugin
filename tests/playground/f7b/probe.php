<?php
/**
 * Plugin Name: Sonda de f7b.sh (sólo Playground)
 * Description: mu-plugin de tests/playground/f7b.sh (F7b, frente L). Nunca forma parte de un plugin.
 *
 * - Captura los correos (`pre_wp_mail`) y las peticiones salientes (`pre_http_request`, el webhook de B10) en la
 *   opción `digi_f7b_outbox`: nada sale del Playground.
 * - Apaga WP-Cron desde las visitas: el recorrido por lotes de F7b (V.6) lo ejecuta la sonda (`?digi_f7b=scan`).
 * - En `wp_footer` 999 imprime `<!-- digi-f7b-probe {…} -->`: `DONOTCACHEPAGE`, la puerta de PRO, el número de
 *   consultas SQL de la petición y los handles encolados.
 * - `?digi_f7b=<op>&token=…`: control del arnés (`digi_f7b_route()` y las operaciones de `ops/*.php`, cada archivo
 *   devuelve `array( op => callable( callable $arg ): array )`).
 *
 * Exige el token aleatorio de `mode.json`. No usa ninguna clase de F7b sin comprobarla antes: debe funcionar igual con
 * 4.3.0.168 instalado (B12).
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}
if ( ! defined( 'SAVEQUERIES' ) && isset( $_GET['digi_f7b_q'] ) ) { // phpcs:ignore
	define( 'SAVEQUERIES', true );
}
require_once WP_CONTENT_DIR . '/probe/lib.php';

/** Correos y peticiones salientes capturados. */
function digi_f7b_outbox( $kind, array $entry ) {
	$box            = get_option( 'digi_f7b_outbox', array() );
	$box            = is_array( $box ) ? $box : array();
	$entry['at']    = microtime( true );
	$box[ $kind ][] = $entry;
	$box[ $kind ]   = array_slice( $box[ $kind ], -20 );
	update_option( 'digi_f7b_outbox', $box, false );
}
add_filter(
	'pre_wp_mail',
	static function ( $null, $atts ) {
		digi_f7b_outbox( 'mail', array( 'to' => $atts['to'] ?? '', 'subject' => (string) ( $atts['subject'] ?? '' ), 'message' => (string) ( $atts['message'] ?? '' ) ) );
		return true;
	},
	10,
	2
);
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( '' !== $host && false !== strpos( home_url(), $host ) ) {
			return $pre;
		}
		digi_f7b_outbox( 'http', array( 'url' => (string) $url, 'method' => (string) ( $args['method'] ?? 'GET' ), 'body' => is_array( $args['body'] ?? null ) ? $args['body'] : (string) ( $args['body'] ?? '' ) ) );
		return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
	},
	10,
	3
);

// Comentario de la sonda al final de cada página del front.
add_action(
	'wp_footer',
	static function () {
		if ( is_admin() ) {
			return;
		}
		global $wpdb, $wp_scripts, $wp_styles;
		$gate = null;
		if ( class_exists( '\\Digitalisimo\\Elements\\Front\\Pro_Gate' ) && method_exists( '\\Digitalisimo\\Elements\\Front\\Pro_Gate', 'reasons' ) ) {
			$gate = \Digitalisimo\Elements\Front\Pro_Gate::reasons();
		}
		$data = array(
			'donotcache' => defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE,
			'queries'    => isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries : -1,
			'scripts'    => $wp_scripts ? array_values( (array) $wp_scripts->queue ) : array(),
			'styles'     => $wp_styles ? array_values( (array) $wp_styles->queue ) : array(),
			'gate'       => $gate,
			'user'       => get_current_user_id(),
			'queried'    => get_queried_object_id(),
		);
		echo "\n<!-- digi-f7b-probe " . wp_json_encode( $data ) . " -->\n";
	},
	999
);

function digi_f7b_send( $data, $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $data );
	exit;
}

/** Recuentos de F7b (`Flags`) leídos de la opción, sin la caché de opciones. */
function digi_f7b_counts() {
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'digitalisimo_elements_f7b_counts' ) ); // phpcs:ignore
	return null === $raw ? null : maybe_unserialize( $raw );
}

function digi_f7b_route() {
	if ( ! isset( $_GET['digi_f7b'] ) ) { // phpcs:ignore
		return;
	}
	$mode = digi_f7b_mode();
	if ( empty( $mode['token'] ) || ! isset( $_GET['token'] ) || ! hash_equals( (string) $mode['token'], (string) $_GET['token'] ) ) { // phpcs:ignore
		status_header( 403 );
		exit;
	}
	$op  = sanitize_key( wp_unslash( $_GET['digi_f7b'] ) ); // phpcs:ignore
	$arg = static function ( $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- sonda de Playground protegida por token.
		$source = isset( $_POST[ $key ] ) ? $_POST : $_GET;
		return isset( $source[ $key ] ) ? wp_unslash( (string) $source[ $key ] ) : $default;
	};
	$map = digi_f7b_ids();
	if ( ! in_array( $op, array( 'ping', 'whoami' ), true ) ) {
		wp_set_current_user( 1 );
	}

	switch ( $op ) {
		case 'ping':
			$state = class_exists( '\\Digitalisimo\\Elements\\F7b\\Actions\\Interactions_State' ) ? \Digitalisimo\Elements\F7b\Actions\Interactions_State::state() : null;
			digi_f7b_send(
				array(
					'ready'        => (int) get_option( 'digi_f7b_ready', 0 ),
					'core'         => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
					'pro'          => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '',
					'bundled'      => defined( 'ELEMENTOR_PATH' ) && false !== strpos( ELEMENTOR_PATH, '/digitalisimo-elements/elementor/' ),
					'f7b'          => class_exists( '\\Digitalisimo\\Elements\\F7b\\Module' ),
					'filters'      => class_exists( '\\Digitalisimo\\Elements\\Filters\\Module' ),
					'counts'       => digi_f7b_counts(),
					'interactions' => $state,
					'cache_ttl'    => get_option( 'elementor_element_cache_ttl', null ),
					'v4'           => get_option( 'elementor_experiment-e_atomic_elements', null ),
					'theme'        => get_stylesheet(),
					'woocommerce'  => defined( 'WC_VERSION' ) ? WC_VERSION : null,
					'front'        => get_option( 'digitalisimo_elements_front', null ),
					'errors'       => (string) @file_get_contents( DIGI_F7B_DIR . '/setup-error.txt' ), // phpcs:ignore
				)
			);
			break;

		case 'whoami':
			digi_f7b_send( array( 'user' => get_current_user_id(), 'nonce' => is_user_logged_in() ? wp_create_nonce( 'elementor_ajax' ) : '', 'rest' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '' ) );
			break;

		case 'ids':
			digi_f7b_send( $map );
			break;

		case 'tree':
			digi_f7b_send( digi_f7b_doc_tree( sanitize_key( $arg( 'doc' ) ), $map ) );
			break;

		case 'data':
			$id = (int) $arg( 'id' );
			if ( ! $id && $arg( 'doc' ) ) {
				$id = (int) ( $map['docs'][ sanitize_key( $arg( 'doc' ) ) ] ?? 0 );
			}
			$raw = get_post_meta( $id, '_elementor_data', true );
			digi_f7b_send( array( 'id' => $id, 'status' => get_post_status( $id ), 'elements' => is_string( $raw ) ? json_decode( $raw, true ) : $raw, 'uses' => get_post_meta( $id, '_digitalisimo_uses', false ) ) );
			break;

		case 'save':
			// Vuelve a guardar un documento de cases.json (o el árbol que llega en `elements`) con Document::save().
			$key = sanitize_key( $arg( 'doc' ) );
			$id  = (int) ( $map['docs'][ $key ] ?? 0 );
			$doc = $id && class_exists( '\\Elementor\\Plugin' ) ? \Elementor\Plugin::$instance->documents->get( $id, false ) : null;
			if ( ! $doc ) {
				digi_f7b_send( array( 'ok' => false, 'error' => 'sin documento' ) );
			}
			$elements = json_decode( $arg( 'elements', '' ), true );
			if ( ! is_array( $elements ) ) {
				$elements = digi_f7b_doc_tree( $key, $map )['elements'];
			}
			$ok = $doc->save( array( 'elements' => $elements, 'settings' => array( 'post_status' => get_post_status( $id ) ) ) );
			digi_f7b_send( array( 'ok' => false !== $ok ) );
			break;

		case 'option':
			// Opciones que los escenarios cambian (lista cerrada).
			$allowed = array( 'elementor_element_cache_ttl', 'digitalisimo_elements_interactions_state', 'elementor_experiment-e_atomic_elements', 'digitalisimo_elements_f7b_counts', 'digitalisimo_elements_front' );
			$name    = $arg( 'name' );
			if ( ! in_array( $name, $allowed, true ) ) {
				digi_f7b_send( array( 'error' => 'opción no permitida' ), 400 );
			}
			if ( '' !== $arg( 'value', '' ) ) {
				$value = json_decode( $arg( 'value' ), true );
				if ( null === $value && 'null' !== $arg( 'value' ) ) {
					$value = $arg( 'value' );
				}
				if ( null === $value ) {
					delete_option( $name );
				} else {
					update_option( $name, $value, true );
				}
			}
			wp_cache_delete( 'alloptions', 'options' );
			digi_f7b_send( array( 'name' => $name, 'value' => get_option( $name, null ) ) );
			break;

		case 'scan':
			// El recorrido por lotes de V.6 hasta el final, en esta petición (sin WP-Cron).
			$scan = '\\Digitalisimo\\Elements\\F7b\\Scan';
			if ( ! class_exists( $scan ) ) {
				digi_f7b_send( array( 'ok' => false, 'error' => 'sin Scan' ) );
			}
			if ( '1' === $arg( 'restart', '1' ) ) {
				$scan::schedule();
			}
			$rounds = 0;
			while ( $scan::running() && $rounds < 40 ) {
				$scan::run();
				++$rounds;
				wp_cache_delete( 'alloptions', 'options' );
			}
			wp_clear_scheduled_hook( $scan::HOOK );
			$state = class_exists( '\\Digitalisimo\\Elements\\F7b\\Actions\\Interactions_State' ) ? \Digitalisimo\Elements\F7b\Actions\Interactions_State::state() : null;
			digi_f7b_send( array( 'ok' => true, 'rounds' => $rounds, 'running' => $scan::running(), 'counts' => digi_f7b_counts(), 'interactions' => $state, 'inventory' => get_option( 'digitalisimo_elements_interactions_inventory', null ) ) );
			break;

		case 'clearcss':
			if ( class_exists( '\\Elementor\\Plugin' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
			digi_f7b_send( array( 'ok' => true ) );
			break;

		case 'meta':
			$id = (int) $arg( 'id' );
			if ( ! $id && $arg( 'doc' ) ) {
				$id = (int) ( $map['docs'][ sanitize_key( $arg( 'doc' ) ) ] ?? 0 );
			}
			$key = $arg( 'key' );
			if ( ! in_array( $key, array( '_elementor_element_cache', '_digitalisimo_uses', '_elementor_css', '_elementor_page_assets' ), true ) ) {
				digi_f7b_send( array( 'error' => 'meta no permitida' ), 400 );
			}
			$values = get_post_meta( $id, $key, false );
			digi_f7b_send( array( 'id' => $id, 'values' => $values, 'json' => wp_json_encode( $values ) ) );
			break;

		case 'wpquery':
			// WP_Query directo con los argumentos dados: el conjunto con el que se comparan bucles y filtros.
			$args = json_decode( $arg( 'args', '{}' ), true );
			$args = is_array( $args ) ? $args : array();
			$args = array_merge( array( 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'fields' => 'ids', 'suppress_filters' => false ), $args );
			wp_set_current_user( 0 );
			$query = new WP_Query( $args );
			digi_f7b_send( array( 'ids' => array_map( 'intval', $query->posts ), 'found' => (int) $query->found_posts, 'request' => $query->request ) );
			break;

		case 'outbox':
			$box = get_option( 'digi_f7b_outbox', array() );
			if ( '1' === $arg( 'clear' ) ) {
				delete_option( 'digi_f7b_outbox' );
			}
			digi_f7b_send( is_array( $box ) ? $box : array() );
			break;

		case 'posts':
			// Las entradas de prueba con lo que los escenarios necesitan para calcular lo esperado.
			$out = array();
			foreach ( (array) ( $map['posts'] ?? array() ) as $key => $id ) {
				$post        = get_post( (int) $id );
				$out[ $key ] = array(
					'id'     => (int) $id,
					'title'  => $post ? $post->post_title : '',
					'status' => $post ? $post->post_status : '',
					'author' => $post ? (int) $post->post_author : 0,
					'date'   => $post ? $post->post_date : '',
					'cats'   => wp_get_post_categories( (int) $id ),
					'precio' => get_post_meta( (int) $id, 'precio', true ),
					'color'  => get_post_meta( (int) $id, 'color', true ),
				);
			}
			digi_f7b_send( $out );
			break;
	}
	// Operaciones de cada grupo de escenarios (ops/*.php): cada archivo devuelve array( op => callable ).
	foreach ( glob( DIGI_F7B_DIR . '/ops/*.php' ) ?: array() as $file ) {
		$ops = require $file;
		if ( is_array( $ops ) && isset( $ops[ $op ] ) && is_callable( $ops[ $op ] ) ) {
			digi_f7b_send( call_user_func( $ops[ $op ], $arg, $map ) );
		}
	}
	digi_f7b_send( array( 'error' => 'operación desconocida: ' . $op ), 404 );
}
add_action( 'wp_loaded', 'digi_f7b_route', 1 );
