<?php
/**
 * Plugin Name: DIGI A/B del tema (sólo pruebas)
 * Description: Ruta de la prueba A/B (`?digi-ab=<op>&token=…`), registro de errores de PHP, de avisos de obsolescencia,
 *              de errores fatales y de peticiones HTTP salientes, todas bloqueadas (P-5: nunca se ofrece una versión
 *              sobre carpetas montadas, y con un solo worker una petición a sí mismo no terminaría). No se publica.
 */
defined( 'ABSPATH' ) || exit;

const DIGI_AB_DIR = '/wordpress/wp-content/probe';

// El cron sólo corre cuando la prueba lo pide (S3c): ninguna visita lo dispara por su cuenta.
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}

function digi_ab_log( $file, array $row ) {
	$row += array(
		'uri'   => isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '',
		'theme' => (string) get_option( 'stylesheet' ),
		'phase' => isset( $_COOKIE['digi_ab_phase'] ) ? (string) $_COOKIE['digi_ab_phase'] : (string) get_option( 'digi_ab_phase', '' ),
	);
	file_put_contents( DIGI_AB_DIR . '/' . $file, wp_json_encode( $row ) . "\n", FILE_APPEND );
}

// Advertencias, avisos y obsolescencias de PHP (aunque WP_DEBUG esté apagado); se respeta el operador @.
set_error_handler(
	static function ( $no, $message, $file, $line ) {
		$kinds = array( E_WARNING => 'Warning', E_USER_WARNING => 'Warning', E_NOTICE => 'Notice', E_USER_NOTICE => 'Notice', E_DEPRECATED => 'Deprecated', E_USER_DEPRECATED => 'Deprecated' );
		if ( isset( $kinds[ $no ] ) && 4437 !== error_reporting() ) {
			digi_ab_log( 'php.jsonl', array( 'kind' => $kinds[ $no ], 'msg' => $message, 'file' => $file, 'line' => $line ) );
		}
		return false;
	}
);
foreach ( array( 'deprecated_function_run', 'deprecated_constructor_run', 'deprecated_class_run', 'deprecated_file_included', 'deprecated_argument_run', 'deprecated_hook_run', 'doing_it_wrong_run' ) as $digi_ab_hook ) {
	add_action(
		$digi_ab_hook,
		static function ( ...$args ) use ( $digi_ab_hook ) {
			$parts = array();
			foreach ( $args as $arg ) {
				$parts[] = is_scalar( $arg ) ? (string) $arg : gettype( $arg );
			}
			digi_ab_log( 'php.jsonl', array( 'kind' => 'doing_it_wrong_run' === $digi_ab_hook ? 'DoingItWrong' : 'Deprecated', 'msg' => $digi_ab_hook . ': ' . implode( ' | ', $parts ), 'file' => wp_debug_backtrace_summary( null, 3 ), 'line' => 0 ) );
		},
		10,
		4
	);
}
register_shutdown_function(
	static function () {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			$phase = isset( $_COOKIE['digi_ab_phase'] ) ? (string) $_COOKIE['digi_ab_phase'] : ( function_exists( 'get_option' ) ? (string) get_option( 'digi_ab_phase', '' ) : '' );
			file_put_contents( DIGI_AB_DIR . '/fatal.log', '[' . $phase . '] ' . ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line'] . "\n", FILE_APPEND );
		}
	}
);

// Ninguna petición sale del sitio: GitHub incluido (P-5), y también las que el sitio se haría a sí mismo.
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		digi_ab_log( 'http.jsonl', array( 'url' => (string) $url, 'host' => (string) wp_parse_url( $url, PHP_URL_HOST ), 'caller' => wp_debug_backtrace_summary( null, 3 ) ) );
		return new WP_Error( 'digi_ab_offline', 'Prueba A/B sin red: ' . $url );
	},
	PHP_INT_MAX,
	3
);
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );

// Hoja de un tercero que depende de la de Hello (P-4): con el tema propio en paridad debe seguir imprimiéndose por el
// alias. Se encola sólo si la dependencia existe, como haría un plugin cuidadoso: así su ausencia en B se ve en la
// comprobación «deps» sin ensuciar el registro de PHP del modo base (D), donde no hay alias por diseño.
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( wp_style_is( 'hello-elementor-theme-style', 'registered' ) ) {
			wp_enqueue_style( 'digi-ab-dep', content_url( 'mu-plugins/digi-ab-dep.css' ), array( 'hello-elementor-theme-style' ), '1' );
		}
	},
	30
);

// Fase D: paridad apagada a la fuerza para ver el modo base.
if ( get_option( 'digi_ab_parity_off' ) ) {
	add_filter( 'digitalisimo_theme_parity', '__return_false', PHP_INT_MAX );
}

add_action( 'wp_loaded', 'digi_ab_route', 1 );

function digi_ab_route() {
	if ( ! isset( $_GET['digi-ab'] ) ) {
		return;
	}
	$token = trim( (string) @file_get_contents( DIGI_AB_DIR . '/token' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( '' === $token || ! hash_equals( $token, (string) wp_unslash( $_GET['token'] ?? '' ) ) ) {
		status_header( 403 );
		exit( 'token' );
	}
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	$op = sanitize_key( wp_unslash( $_GET['digi-ab'] ) );
	ob_start(); // Lo que se imprima por el camino (avisos de PHP incluidos) no rompe el JSON: se devuelve aparte.
	try {
		$out = digi_ab_op( $op );
	} catch ( Throwable $e ) {
		$out = array( 'error' => get_class( $e ) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() );
	}
	$stray = (string) ob_get_clean();
	if ( '' !== trim( $stray ) ) {
		$out['stray'] = substr( trim( wp_strip_all_tags( $stray ) ), 0, 800 );
	}
	echo wp_json_encode( $out, JSON_PARTIAL_OUTPUT_ON_ERROR );
	exit;
}

function digi_ab_arg( $key, $default = '' ) {
	return isset( $_GET[ $key ] ) ? (string) wp_unslash( $_GET[ $key ] ) : $default;
}

function digi_ab_op( $op ) {
	switch ( $op ) {
		case 'ping':
			return array( 'ready' => (int) get_option( 'digi_ab_ready' ), 'stylesheet' => get_option( 'stylesheet' ) );
		case 'ids':
			return digi_ab_ids();
		case 'phase':
			update_option( 'digi_ab_phase', digi_ab_arg( 'name' ), false );
			return array( 'phase' => digi_ab_arg( 'name' ) );
		case 'scenario':
			return digi_ab_scenario( digi_ab_arg( 'name' ) );
		case 'switch':
			wp_set_current_user( 1 ); // Un administrador cambia de tema; la primera petición siguiente puede ser de un visitante (S3c).
			$to = digi_ab_arg( 'to' );
			if ( ! wp_get_theme( $to )->exists() ) {
				return array( 'error' => "No existe el tema $to" );
			}
			switch_theme( $to );
			return array( 'stylesheet' => get_option( 'stylesheet' ), 'template' => get_option( 'template' ) );
		case 'revert':
			wp_set_current_user( 1 );
			if ( ! function_exists( 'digitalisimo_theme_revert' ) ) {
				return array( 'error' => 'Falta digitalisimo_theme_revert() (contrato §7.12)', 'stylesheet' => get_option( 'stylesheet' ) );
			}
			$result = digitalisimo_theme_revert( array( 'force' => '1' === digi_ab_arg( 'force' ) ) );
			return array(
				'ok'         => ! is_wp_error( $result ),
				'code'       => is_wp_error( $result ) ? $result->get_error_code() : '',
				'message'    => is_wp_error( $result ) ? $result->get_error_message() : '',
				'stylesheet' => get_option( 'stylesheet' ),
			);
		case 'import-child-css':
			wp_set_current_user( 1 );
			if ( ! function_exists( 'digitalisimo_theme_import_previous' ) ) {
				return array( 'error' => 'Falta digitalisimo_theme_import_previous() (contrato §7.12)' );
			}
			// Lo mismo que «wp digitalisimo-theme import --from=digi-hello-child --child-css» (§7.13).
			$result = digitalisimo_theme_import_previous( wp_get_theme( digi_ab_arg( 'from', 'digi-hello-child' ) ), array( 'force' => true, 'child_css' => true ) );
			return array( 'ok' => ! is_wp_error( $result ), 'message' => is_wp_error( $result ) ? $result->get_error_message() : '', 'css' => wp_get_custom_css( get_stylesheet() ), 'childUri' => get_theme_root_uri( 'digi-hello-child' ) . '/digi-hello-child/' );
		case 'record':
			return digi_ab_record();
		case 'mods-hash':
			return digi_ab_mods_hash();
		case 'cron':
			return digi_ab_cron( preg_replace( '/[^A-Za-z0-9_\-]/', '', digi_ab_arg( 'hook' ) ) );
		case 'nonce':
			return array( 'user' => get_current_user_id(), 'rest' => wp_create_nonce( 'wp_rest' ), 'ajax' => wp_create_nonce( 'elementor_ajax' ) );
		case 'experiment':
			// F2: el asistente nuevo (con su ruta REST) sólo existe con el experimento oculto e_onboarding activo.
			$name = sanitize_key( digi_ab_arg( 'name' ) );
			if ( in_array( digi_ab_arg( 'state' ), array( 'active', 'inactive' ), true ) ) {
				update_option( 'elementor_experiment-' . $name, digi_ab_arg( 'state' ) );
			} else {
				delete_option( 'elementor_experiment-' . $name );
			}
			return array( 'option' => get_option( 'elementor_experiment-' . $name, null ) );
		case 'preview-url':
			$document = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance->documents->get( (int) digi_ab_arg( 'id' ) ) : null;
			return array( 'url' => $document ? $document->get_preview_url() : '' );
		case 'parity-off':
			if ( '1' === digi_ab_arg( 'on' ) ) {
				update_option( 'digi_ab_parity_off', 1 );
			} else {
				delete_option( 'digi_ab_parity_off' );
			}
			return array( 'parityOff' => (bool) get_option( 'digi_ab_parity_off' ) );
		case 'elements':
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			if ( '1' === digi_ab_arg( 'on' ) ) {
				$result = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
			} else {
				deactivate_plugins( 'digitalisimo-elements/pro-elements.php' );
				$result = null;
			}
			return array( 'active' => is_plugin_active( 'digitalisimo-elements/pro-elements.php' ), 'error' => is_wp_error( $result ) ? $result->get_error_message() : '' );
	}
	return array( 'error' => "Operación desconocida: $op" );
}

function digi_ab_path( $url ) {
	return (string) wp_make_link_relative( $url );
}

function digi_ab_ids() {
	$pages = array();
	foreach ( array( 'digi-ts-constructor', 'digi-ts-canvas', 'digi-ts-ancho-completo', 'digi-ts-corta', 'digi-ts-carrusel', 'digi-ts-sin-titulo', 'digi-ts-hijo', 'digi-ts-blog' ) as $slug ) {
		$page = get_page_by_path( $slug );
		$pages[ $slug ] = $page ? digi_ab_path( get_permalink( $page ) ) : '';
	}
	$post     = get_page_by_path( 'digi-ts-entrada', OBJECT, 'post' );
	$category = get_term_by( 'slug', 'digi-ts-cat', 'category' );
	$woo      = array();
	if ( function_exists( 'wc_get_page_id' ) ) {
		$product = get_page_by_path( 'digi-ts-producto', OBJECT, 'product' );
		$woo     = array(
			'shop'      => digi_ab_path( get_permalink( wc_get_page_id( 'shop' ) ) ),
			'cart'      => digi_ab_path( get_permalink( wc_get_page_id( 'cart' ) ) ),
			'product'   => $product ? digi_ab_path( get_permalink( $product ) ) : '',
			'productId' => $product ? $product->ID : 0,
		);
	}
	return array(
		'pages'        => $pages,
		'post'         => $post ? digi_ab_path( get_permalink( $post ) ) : '',
		'category'     => $category ? digi_ab_path( get_term_link( $category ) ) : '',
		'woo'          => $woo,
		'site'         => array( 'name' => get_bloginfo( 'name' ), 'tagline' => get_bloginfo( 'description' ) ),
		'defaultTheme' => WP_DEFAULT_THEME,
		'defaultOk'    => wp_get_theme( WP_DEFAULT_THEME )->exists(),
		'themeVersion' => (string) wp_get_theme( 'digitalisimo-theme' )->get( 'Version' ),
		'helloVersion' => (string) wp_get_theme( 'hello-elementor' )->get( 'Version' ),
		'php'          => PHP_VERSION,
		'wp'           => get_bloginfo( 'version' ),
	);
}

/** Deja el sitio en el punto de partida del escenario: Hello (o su hijo) activo y sin rastro de un cambio anterior. */
function digi_ab_scenario( $name ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$valid = array( 'S1', 'S2', 'S3', 'S3c', 'S3h', 'S4', 'S5' );
	if ( ! in_array( $name, $valid, true ) ) {
		return array( 'error' => "Escenario desconocido: $name" );
	}
	wp_set_current_user( 1 );
	$notes = array();
	delete_option( 'digi_ab_parity_off' );
	update_option( 'digi_ab_scenario', $name, false );

	// SEO activo salvo en S5 (FR-10).
	$seo = 'digitalisimo-seo/digitalisimo-integrations.php';
	if ( 'S5' === $name ) {
		deactivate_plugins( $seo );
	} elseif ( ! is_plugin_active( $seo ) ) {
		$activated = activate_plugin( $seo );
		if ( is_wp_error( $activated ) ) {
			$notes[] = 'SEO: ' . $activated->get_error_message();
		}
	}
	if ( ! is_plugin_active( 'digitalisimo-elements/pro-elements.php' ) ) {
		activate_plugin( 'digitalisimo-elements/pro-elements.php' );
	}

	// Rastro del tema propio: registro, bitácora, mods, CSS adicional, cerrojos, evento y plantillas generadas o de S2.
	foreach ( array( 'digitalisimo_theme_switch', 'digitalisimo_theme_switch_log', 'theme_mods_digitalisimo-theme', 'digitalisimo_theme_switch_lock', 'digitalisimo_theme_convert_lock' ) as $option ) {
		delete_option( $option );
	}
	wp_clear_scheduled_hook( 'digitalisimo_theme_convert_hello' );
	$css = wp_get_custom_css_post( 'digitalisimo-theme' );
	if ( $css ) {
		wp_delete_post( $css->ID, true );
	}
	foreach ( digi_ab_library( array( '_digitalisimo_generated', '_digi_ab_tb' ) ) as $post ) {
		wp_delete_post( $post->ID, true );
	}
	foreach ( array( 'description_meta_tag', 'skip_link', 'header_footer', 'page_title', 'hello_style', 'hello_theme' ) as $key ) {
		delete_option( 'hello_elementor_settings_' . $key );
	}

	// Cabecera y pie dinámicos de Hello (S3*) o clásicos; el experimento nuevo viene activo por defecto (≥ 3.3.0).
	$dynamic = in_array( $name, array( 'S3', 'S3c', 'S3h' ), true );
	update_option( 'elementor_experiment-hello-theme-header-footer', $dynamic ? 'active' : 'inactive' );
	$kit_id = (int) get_option( 'elementor_active_kit' );
	if ( $kit_id ) {
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : array();
		foreach ( array_keys( $settings ) as $key ) {
			if ( 0 === strpos( (string) $key, 'hello_' ) ) {
				unset( $settings[ $key ] );
			}
		}
		if ( $dynamic ) {
			$settings += array(
				'hello_header_logo_display'      => 'yes',
				'hello_header_tagline_display'   => 'yes',
				'hello_header_menu_display'      => 'yes',
				'hello_header_logo_type'         => 'title',
				'hello_header_layout'            => 'default',
				'hello_header_width'             => 'boxed',
				'hello_header_menu_dropdown'     => 'tablet',
				'hello_header_menu_layout'       => 'horizontal',
				'hello_footer_logo_display'      => 'yes',
				'hello_footer_tagline_display'   => 'yes',
				'hello_footer_menu_display'      => 'yes',
				'hello_footer_copyright_display' => 'yes',
				'hello_footer_logo_type'         => 'title',
				'hello_footer_copyright_text'    => 'Pie de prueba AB',
			);
			if ( 'S3h' === $name ) {
				// Todo apagado: Hello no pinta la cabecera (modo «hidden») y pinta el pie vacío, con su relleno (el
				// tema genera ese contenedor vacío).
				foreach ( array( 'header_logo', 'header_tagline', 'header_menu', 'footer_logo', 'footer_tagline', 'footer_menu', 'footer_copyright' ) as $switch ) {
					$settings[ 'hello_' . $switch . '_display' ] = '';
				}
			}
		}
		update_post_meta( $kit_id, '_elementor_page_settings', $settings );
	} else {
		$notes[] = 'Sin kit activo';
	}

	// S2: plantillas del constructor de temas para las cuatro ubicaciones, con «include/general».
	$templates = array();
	if ( 'S2' === $name ) {
		$templates = digi_ab_theme_builder();
	}

	// S4 parte del hijo de Hello, con una página que usa su plantilla propia.
	$target = 'S4' === $name ? 'digi-hello-child' : 'hello-elementor';
	$child  = get_page_by_path( 'digi-ts-hijo' );
	if ( $child ) {
		if ( 'S4' === $name ) {
			update_post_meta( $child->ID, '_wp_page_template', 'page-digi-child.php' );
		} else {
			delete_post_meta( $child->ID, '_wp_page_template' );
		}
	}
	if ( get_option( 'stylesheet' ) !== $target ) {
		switch_theme( $target );
	}
	$menus = array();
	foreach ( array( 'menu-1' => 'digi-ab-principal', 'menu-2' => 'digi-ab-pie' ) as $location => $slug ) {
		$menu = wp_get_nav_menu_object( $slug );
		if ( $menu ) {
			$menus[ $location ] = (int) $menu->term_id;
		}
	}
	set_theme_mod( 'nav_menu_locations', $menus );
	wp_update_custom_css_post( '.digi-ts-marker{background:#ffeb3b;padding:4px}', array( 'stylesheet' => $target ) );
	update_option( 'theme_switched', '' ); // El cambio de reinicio no debe contar como «tema anterior» de nada.

	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	digi_ab_conditions_cache();
	return array( 'scenario' => $name, 'stylesheet' => get_option( 'stylesheet' ), 'templates' => $templates, 'kit' => $kit_id, 'notes' => $notes );
}

function digi_ab_library( array $meta_keys ) {
	$query = array( 'relation' => 'OR' );
	foreach ( $meta_keys as $key ) {
		$query[] = array( 'key' => $key, 'compare' => 'EXISTS' );
	}
	return get_posts( array( 'post_type' => 'elementor_library', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'numberposts' => -1, 'meta_query' => $query ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
}

function digi_ab_conditions_cache() {
	if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
		$cache = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_cache();
		if ( method_exists( $cache, 'regenerate' ) ) {
			$cache->regenerate();
		}
	}
}

/** Widget en el formato que recibe Document::save() (arrays, como los decodifica el editor). */
function digi_ab_widget( $id, $type, array $settings = array() ) {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
}

function digi_ab_theme_builder() {
	if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
		return array( 'error' => 'Falta el constructor de temas' );
	}
	$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
	// Los widgets de título llevan su etiqueta dinámica (el editor la pone al crearlos; save() no aplica ese valor).
	$tag   = static function ( $name ) {
		return array( '__dynamic__' => array( 'title' => '[elementor-tag id="" name="' . $name . '" settings="%7B%7D"]' ) );
	};
	$trees = array(
		'header'  => array( digi_ab_widget( 'dabth01', 'theme-site-title', $tag( 'site-title' ) ), digi_ab_widget( 'dabth02', 'heading', array( 'title' => 'Cabecera del constructor', 'header_size' => 'p' ) ) ),
		'footer'  => array( digi_ab_widget( 'dabtf01', 'heading', array( 'title' => 'Pie del constructor', 'header_size' => 'p' ) ) ),
		'single'  => array( digi_ab_widget( 'dabts01', 'theme-post-title', $tag( 'post-title' ) ), digi_ab_widget( 'dabts02', 'theme-post-content' ) ),
		'archive' => array( digi_ab_widget( 'dabta01', 'theme-archive-title', $tag( 'archive-title' ) ), digi_ab_widget( 'dabta02', 'archive-posts' ) ),
	);
	$ids = array();
	foreach ( $trees as $type => $widgets ) {
		$document = \Elementor\Plugin::$instance->documents->create( $type, array( 'post_title' => 'AB ' . $type, 'post_status' => 'publish' ), array( '_digi_ab_tb' => 1 ) );
		if ( ! $document || is_wp_error( $document ) ) {
			$ids[ $type ] = 'error: ' . ( is_wp_error( $document ) ? $document->get_error_message() : 'sin documento' );
			continue;
		}
		$document->save( array( 'elements' => array( array( 'id' => 'dabtc' . substr( md5( $type ), 0, 2 ), 'elType' => 'container', 'settings' => array(), 'elements' => $widgets ) ) ) );
		$manager->save_conditions( $document->get_main_id(), array( array( 'type' => 'include', 'name' => 'general' ) ) );
		$ids[ $type ] = $document->get_main_id();
	}
	return $ids;
}

function digi_ab_record() {
	global $wpdb;
	wp_cache_delete( 'alloptions', 'options' );
	foreach ( array( 'digitalisimo_theme_switch', 'digitalisimo_theme_switch_log', 'stylesheet', 'template' ) as $option ) {
		wp_cache_delete( $option, 'options' );
	}
	$generated = array();
	foreach ( digi_ab_library( array( '_digitalisimo_generated' ) ) as $post ) {
		$generated[] = array( 'id' => $post->ID, 'status' => $post->post_status, 'part' => get_post_meta( $post->ID, '_digitalisimo_generated', true ), 'type' => get_post_meta( $post->ID, '_elementor_template_type', true ), 'conditions' => get_post_meta( $post->ID, '_elementor_conditions', true ) );
	}
	$tb = array();
	foreach ( digi_ab_library( array( '_digi_ab_tb' ) ) as $post ) {
		$tb[ get_post_meta( $post->ID, '_elementor_template_type', true ) ] = array( 'id' => $post->ID, 'status' => $post->post_status );
	}
	$record = get_option( 'digitalisimo_theme_switch', null );
	return array(
		'stylesheet'   => get_option( 'stylesheet' ),
		'template'     => get_option( 'template' ),
		'record'       => $record,
		'recordBytes'  => null === $record ? 0 : strlen( maybe_serialize( $record ) ),
		'autoload'     => $wpdb->get_results( $wpdb->prepare( "SELECT option_name, autoload FROM {$wpdb->options} WHERE option_name IN (%s, %s)", 'digitalisimo_theme_switch', 'digitalisimo_theme_switch_log' ), OBJECT_K ),
		'log'          => get_option( 'digitalisimo_theme_switch_log', null ),
		'parity'       => function_exists( 'digitalisimo_theme_parity' ) ? (bool) digitalisimo_theme_parity() : null,
		'generated'    => $generated,
		'tb'           => $tb,
		'scheduled'    => wp_next_scheduled( 'digitalisimo_theme_convert_hello' ),
		'customCss'    => wp_get_custom_css( get_option( 'stylesheet' ) ),
		'themeSwitched' => get_option( 'theme_switched' ),
		'lockTheme'    => get_option( 'digitalisimo_theme_switch_lock', null ),
		'lockConvert'  => get_option( 'digitalisimo_theme_convert_lock', null ),
		'functions'    => array(
			'revert'  => function_exists( 'digitalisimo_theme_revert' ),
			'import'  => function_exists( 'digitalisimo_theme_import_previous' ),
			'record'  => function_exists( 'digitalisimo_theme_switch_record' ),
			'parity'  => function_exists( 'digitalisimo_theme_parity' ),
			'compat'           => class_exists( '\Digitalisimo\Elements\Theme_Compat' ),
			'switched_hook'    => (bool) has_action( 'digitalisimo_theme_switched' ),
			'reverted_hook'    => (bool) has_action( 'digitalisimo_theme_reverted' ),
			'convert_hook'     => (bool) has_action( 'digitalisimo_theme_convert_hello' ),
			'locations_filter' => (bool) has_filter( 'digitalisimo_conditions_theme_locations' ),
		),
	);
}

function digi_ab_sorted( $value ) {
	if ( is_array( $value ) ) {
		ksort( $value );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = digi_ab_sorted( $item );
		}
	}
	return $value;
}

/** md5 de los mods de Hello (y del hijo) sin `sidebars_widgets` (FR-15) y de los ajustes de Hello. */
function digi_ab_mods_hash() {
	$out = array();
	foreach ( array( 'hello' => 'hello-elementor', 'child' => 'digi-hello-child' ) as $key => $stylesheet ) {
		wp_cache_delete( 'theme_mods_' . $stylesheet, 'options' );
		$mods = get_option( 'theme_mods_' . $stylesheet, null );
		if ( is_array( $mods ) ) {
			unset( $mods['sidebars_widgets'] );
			$mods = digi_ab_sorted( $mods );
		}
		$out[ $key ] = array( 'md5' => null === $mods ? null : md5( serialize( $mods ) ), 'keys' => is_array( $mods ) ? array_keys( $mods ) : array() );
	}
	$settings = array();
	foreach ( array( 'description_meta_tag', 'skip_link', 'header_footer', 'page_title', 'hello_style', 'hello_theme' ) as $key ) {
		wp_cache_delete( 'hello_elementor_settings_' . $key, 'options' );
		$settings[ $key ] = get_option( 'hello_elementor_settings_' . $key, '__ausente__' );
	}
	$out['settings']    = md5( serialize( $settings ) );
	$out['settingsRaw'] = $settings;
	return $out;
}

/** Ejecuta los eventos de cron vencidos, como wp-cron.php (S3c: la conversión que dejó pendiente una visita). */
function digi_ab_cron( $only ) {
	if ( ! defined( 'DOING_CRON' ) ) {
		define( 'DOING_CRON', true );
	}
	$ran     = array();
	$skipped = array();
	foreach ( (array) wp_get_ready_cron_jobs() as $timestamp => $hooks ) {
		foreach ( $hooks as $hook => $events ) {
			if ( '' !== $only && $hook !== $only ) {
				$skipped[] = $hook;
				continue;
			}
			foreach ( $events as $event ) {
				wp_unschedule_event( $timestamp, $hook, $event['args'] );
				do_action_ref_array( $hook, $event['args'] );
				$ran[] = $hook;
			}
		}
	}
	return array( 'ran' => $ran, 'skipped' => array_values( array_unique( $skipped ) ), 'user' => get_current_user_id() );
}
