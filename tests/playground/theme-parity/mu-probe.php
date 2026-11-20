<?php
/**
 * Plugin Name: Sonda de theme-parity.sh (sólo pruebas)
 * Description: Rutas `?tp=<op>&token=…` de la prueba de paridad del tema, registro de errores de PHP, ninguna petición
 *              fuera del sitio y, con VIA=migrar, los ZIP locales del índice de la Release. No se publica.
 */
defined( 'ABSPATH' ) || exit;

const TP_DIR = '/wordpress/wp-content/probe';

function tp_log( $file, array $row ) {
	$row += array( 'uri' => (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 'theme' => (string) get_option( 'stylesheet' ), 'blog' => get_current_blog_id() );
	file_put_contents( TP_DIR . '/' . $file, wp_json_encode( $row ) . "\n", FILE_APPEND );
}

set_error_handler(
	static function ( $no, $message, $file, $line ) {
		$kinds = array( E_WARNING => 'Warning', E_USER_WARNING => 'Warning', E_NOTICE => 'Notice', E_USER_NOTICE => 'Notice' );
		if ( isset( $kinds[ $no ] ) && 4437 !== error_reporting() && false === strpos( (string) $file, '/wp-includes/' ) ) {
			tp_log( 'php.jsonl', array( 'kind' => $kinds[ $no ], 'msg' => $message, 'file' => $file, 'line' => $line ) );
		}
		return false;
	}
);
register_shutdown_function(
	static function () {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( TP_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line'] . "\n", FILE_APPEND );
		}
	}
);

// Índice de la Release con ZIP locales (VIA=migrar): Migrar instala Backups, Elements y el tema sin salir del sitio.
add_action( 'muplugins_loaded', static function () {
	$manifest = json_decode( (string) @file_get_contents( TP_DIR . '/manifest.json' ), true );
	if ( is_array( $manifest ) && ! is_array( get_site_transient( 'digitalisimo_plugins_manifest' ) ) ) {
		set_site_transient( 'digitalisimo_plugins_manifest', $manifest, 0 );
	}
} );
add_filter( 'upgrader_pre_download', static function ( $reply, $package ) {
	$local = '/tp-zips/' . basename( (string) $package );
	if ( ! is_file( $local ) ) {
		return new WP_Error( 'tp_missing', 'ZIP ausente en la prueba: ' . basename( (string) $package ) );
	}
	$tmp = wp_tempnam( basename( (string) $package ) );
	copy( $local, $tmp );
	return $tmp;
}, 10, 2 );

// Sin red: sólo el propio sitio. En la red (http://playground.test, sin puerto) la petición va al servidor local.
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( false !== strpos( (string) $url, 'wp-cron.php' ) ) {
		return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	}
	$port = (int) @file_get_contents( TP_DIR . '/port' );
	if ( 'playground.test' === $host && $port ) {
		$cookies = array();
		for ( $hop = 0; $hop < 5; $hop++ ) {
			$local   = preg_replace( '#^https?://playground\.test#', 'http://127.0.0.1:' . $port, $url );
			$header  = "Host: playground.test\r\n" . ( $cookies ? 'Cookie: ' . implode( '; ', $cookies ) . "\r\n" : '' );
			$context = stream_context_create( array( 'http' => array( 'method' => 'GET', 'header' => $header, 'ignore_errors' => true, 'timeout' => (float) ( $args['timeout'] ?? 20 ), 'follow_location' => 0 ) ) );
			$body    = @file_get_contents( $local, false, $context );
			if ( false === $body ) {
				return new WP_Error( 'tp_loopback', 'La petición local falló.' );
			}
			$code     = 0;
			$location = '';
			foreach ( (array) ( $http_response_header ?? array() ) as $line ) {
				if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $line, $m ) ) {
					$code = (int) $m[1];
				} elseif ( preg_match( '#^Set-Cookie:\s*([^;]+)#i', $line, $m ) ) {
					$cookies[] = trim( $m[1] );
				} elseif ( preg_match( '#^Location:\s*(\S+)#i', $line, $m ) ) {
					$location = trim( $m[1] );
				}
			}
			if ( $code < 300 || $code >= 400 || '' === $location ) {
				break;
			}
			$url = 0 === strpos( $location, '/' ) ? 'http://playground.test' . $location : $location;
		}
		return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}
	if ( in_array( $host, array( '127.0.0.1', 'localhost', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), true ) ) {
		return $pre;
	}
	file_put_contents( TP_DIR . '/http-blocked.log', $url . "\n", FILE_APPEND );
	return new WP_Error( 'tp_offline', 'Sin red en la prueba.' );
}, 1, 3 );

// BREAK=1: con el tema DIGITALÍSIMO activo, la portada pierde el CSS adicional (algo deja de verse igual): «Migrar» debe
// notarlo y volver al tema anterior.
add_action( 'wp_head', static function () {
	if ( get_site_option( 'tp_break' ) && 'digitalisimo-theme' === get_template() ) {
		remove_action( 'wp_head', 'wp_custom_css_cb', 101 );
	}
}, 0 );

/**
 * Kit con colores y tipografías globales y «Estilo del tema»; cabecera y pie del constructor en los sitios con hijo. Se
 * pide en la dirección de cada sitio (el constructor guarda en memoria sus documentos por ID).
 */
function tp_prepare() {
	$kind = (string) get_option( 'tp_kind' );
	{
		$px       = static function ( $size ) {
			return array( 'unit' => 'px', 'size' => $size, 'sizes' => array() );
		};
		$box      = static function ( $n ) {
			return array( 'unit' => 'px', 'top' => (string) $n, 'right' => (string) $n, 'bottom' => (string) $n, 'left' => (string) $n, 'isLinked' => true );
		};
		$settings = array(
			'system_colors'                  => array(
				array( '_id' => 'primary', 'title' => 'Primary', 'color' => '#1D4ED8' ),
				array( '_id' => 'secondary', 'title' => 'Secondary', 'color' => '#9333EA' ),
				array( '_id' => 'text', 'title' => 'Text', 'color' => '#374151' ),
				array( '_id' => 'accent', 'title' => 'Accent', 'color' => '#DC2626' ),
			),
			'custom_colors'                  => array( array( '_id' => 'tp0001', 'title' => 'Marca', 'color' => '#0F766E' ) ),
			'system_typography'              => array(
				array( '_id' => 'primary', 'title' => 'Primary', 'typography_typography' => 'custom', 'typography_font_family' => 'Georgia', 'typography_font_weight' => '700' ),
				array( '_id' => 'secondary', 'title' => 'Secondary', 'typography_typography' => 'custom', 'typography_font_family' => 'Georgia', 'typography_font_weight' => '400' ),
				array( '_id' => 'text', 'title' => 'Text', 'typography_typography' => 'custom', 'typography_font_family' => 'Verdana', 'typography_font_weight' => '400' ),
				array( '_id' => 'accent', 'title' => 'Accent', 'typography_typography' => 'custom', 'typography_font_family' => 'Verdana', 'typography_font_weight' => '600' ),
			),
			'body_color'                     => '#374151',
			'body_typography_typography'     => 'custom',
			'body_typography_font_family'    => 'Verdana',
			'body_typography_font_size'      => $px( 17 ),
			'link_normal_color'              => '#0F766E',
			'link_hover_color'               => '#134E4A',
			'h1_color'                       => '#111827',
			'h1_typography_typography'       => 'custom',
			'h1_typography_font_family'      => 'Georgia',
			'h1_typography_font_size'        => $px( 44 ),
			'h3_color'                       => '#9333EA',
			'h3_typography_typography'       => 'custom',
			'h3_typography_font_size'        => $px( 26 ),
			'button_text_color'              => '#FFFFFF',
			'button_border_border'           => 'solid',
			'button_border_width'            => $box( 2 ),
			'button_border_color'            => '#7F1D1D',
			'button_border_radius'           => $box( 8 ),
			'form_field_text_color'          => '#111827',
			'form_field_background_color'    => '#ECFEFF',
			'form_field_border_border'       => 'solid',
			'form_field_border_width'        => $box( 2 ),
			'form_field_border_color'        => '#0F766E',
			'form_field_border_radius'       => $box( 6 ),
			'form_label_color'               => '#0F766E',
			'__globals__'                    => array(
				'h2_color'                => 'globals/colors?id=primary',
				'button_background_color' => 'globals/colors?id=accent',
				'link_normal_color'       => 'globals/colors?id=tp0001',
			),
		);
		$kit_id   = (int) get_option( 'tp_kit' );
		if ( ! $kit_id ) {
			$kit_id = (int) \Elementor\Plugin::$instance->kits_manager->get_active_id();
			update_option( 'tp_kit', $kit_id );
		}
		$kit = \Elementor\Plugin::$instance->documents->get( $kit_id, false );
		if ( $kit ) {
			$kit->save( array( 'settings' => $settings ) );
		}
		$row = array( 'kit' => $kit_id, 'tb' => array() );
		if ( 'hello' !== $kind && class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) && ! get_option( 'tp_tb' ) ) {
			$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
			$trees   = array(
				'header' => array(
					array( 'id' => 'tph0001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Cabecera del constructor', 'header_size' => 'p' ), 'elements' => array() ),
					array( 'id' => 'tph0002', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Contacto', 'link' => array( 'url' => home_url( '/tp-pagina/' ) ) ), 'elements' => array() ),
					// El mismo texto que la cabecera clásica de Hello (nombre, lema y menú): con VIA=migrar la portada se
					// guarda antes de que existan estas plantillas y se compara su texto al final.
					array( 'id' => 'tph0003', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>' . esc_html( get_bloginfo( 'name' ) . ' · ' . get_bloginfo( 'description' ) ) . ' · Inicio · Entrada · Página</p>' ), 'elements' => array() ),
				),
				'footer' => array(
					array( 'id' => 'tpf0001', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Pie del constructor con <a href="' . esc_url( home_url( '/' ) ) . '">un enlace</a>.</p>' ), 'elements' => array() ),
				),
			);
			foreach ( $trees as $type => $widgets ) {
				$document = \Elementor\Plugin::$instance->documents->create( $type, array( 'post_title' => 'TP ' . $type, 'post_status' => 'publish' ) );
				if ( $document && ! is_wp_error( $document ) ) {
					$document->save( array( 'elements' => array( array( 'id' => 'tpt' . substr( md5( $type ), 0, 4 ), 'elType' => 'container', 'settings' => array(), 'elements' => $widgets ) ) ) );
					$manager->save_conditions( $document->get_main_id(), array( array( 'type' => 'include', 'name' => 'general' ) ) );
					$row['tb'][ $type ] = $document->get_main_id();
				}
			}
			update_option( 'tp_tb', $row['tb'] );
		}
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	return $row;
}

/** Estado de cada sitio: tema, registro y bitácora del paso, CSS adicional del tema activo y mods. */
function tp_state() {
	$out = array();
	foreach ( (array) get_site_option( 'tp_sites', array( 1 => 'child' ) ) as $site_id => $kind ) {
		if ( is_multisite() ) {
			switch_to_blog( (int) $site_id );
		}
		wp_cache_delete( 'alloptions', 'options' );
		$out[ $site_id ] = array(
			'kind'       => $kind,
			'stylesheet' => get_option( 'stylesheet' ),
			'home'       => home_url( '/' ),
			'record'     => get_option( 'digitalisimo_theme_switch' ),
			'child'      => get_option( 'digitalisimo_theme_child' ),
			'log'        => get_option( 'digitalisimo_theme_switch_log' ),
			'css'        => wp_get_custom_css( get_option( 'stylesheet' ) ),
			'mods'       => get_option( 'theme_mods_' . get_option( 'stylesheet' ) ),
			'tools'      => get_option( 'digitalisimo_tools_php' ),
		);
		if ( is_multisite() ) {
			restore_current_blog();
		}
	}
	return array(
		'sites'     => $out,
		'allowed'   => is_multisite() ? array_keys( (array) get_site_option( 'allowedthemes', array() ) ) : array(),
		'themes'    => array_keys( wp_get_themes() ),
		'migration' => class_exists( '\Digitalisimo\Migration\Full' ) ? \Digitalisimo\Migration\Full::last( is_multisite() ) : null,
		'job'       => class_exists( '\Digitalisimo\Migration\Full' ) ? \Digitalisimo\Migration\Full::load( is_multisite() ) : null,
	);
}

add_action(
	'init',
	static function () {
		if ( ! isset( $_GET['tp'] ) ) {
			return;
		}
		$token = trim( (string) @file_get_contents( TP_DIR . '/token' ) );
		if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) {
			return;
		}
		$op = sanitize_key( wp_unslash( $_GET['tp'] ) );
		wp_set_current_user( 1 );
		switch ( $op ) {
			case 'ready':
				$out = array( 'ready' => is_file( TP_DIR . '/setup.done' ) ? 1 : 0 );
				break;
			case 'prepare':
				$out = tp_prepare();
				break;
			case 'state':
				$out = tp_state();
				break;
			case 'enable':
				WP_Theme::network_enable_theme( sanitize_key( wp_unslash( $_GET['theme'] ?? '' ) ) );
				$out = array( 'allowed' => array_keys( (array) get_site_option( 'allowedthemes', array() ) ) );
				break;
			case 'break':
				update_site_option( 'tp_break', 1 );
				$out = array( 'break' => 1 );
				break;
			case 'revert':
				// Como «wp digitalisimo-theme revert --url=<sitio>»: en la dirección del sitio, con su tema cargado.
				$result = function_exists( 'digitalisimo_theme_revert' ) ? digitalisimo_theme_revert() : new WP_Error( 'tp', 'sin digitalisimo_theme_revert()' );
				$out    = array( 'ok' => true === $result, 'error' => is_wp_error( $result ) ? $result->get_error_message() : '', 'stylesheet' => get_option( 'stylesheet' ) );
				break;
			default:
				$out = array( 'error' => 'operación desconocida' );
		}
		wp_send_json( $out );
	},
	1
);
