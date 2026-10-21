<?php
/**
 * Sitio para la auditoría de marca: DIGITALÍSIMO Elements con su núcleo incluido (sin el plugin Elementor),
 * una página construida con el editor (con los vídeos que se pintan con su valor por defecto), una plantilla, un
 * popup, una cabecera del Theme Builder y un selector de idioma por cookie (`digi_locale`).
 * Opciones (audit-options.json, lo escribe brand-audit.sh): scope (quick|full), state (nuevo|migrado|kit) y original
 * (el plugin Elementor de wordpress.org instalado e inactivo, más un Elementor Pro con sólo sus cabeceras).
 * STATE=kit: con el sitio ya en marcha, brand-audit.sh importa tests/sources/kits/tercero-pro3.zip con «wp digitalisimo-elements kit
 * import» (kit-cli.php) y vuelve a llamar a este archivo con `$digi_brand_phase = 'kit'`, que anota la sesión y las páginas.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$digi_dir  = '/wordpress/wp-content/probe';
$digi_opts = json_decode( (string) @file_get_contents( $digi_dir . '/audit-options.json' ), true ) ?: array();
$digi_fail = static function ( $message ) use ( $digi_dir ) {
	file_put_contents( $digi_dir . '/setup-error.txt', $message . "\n", FILE_APPEND );
};

// STATE=kit, después del paso «wp digitalisimo-elements kit import» del blueprint: anota la sesión de K, su informe
// en la pantalla «Kits» y cada documento importado (con su vista previa, porque «agregar» los deja en borrador).
if ( 'kit' === ( $digi_brand_phase ?? '' ) ) {
	global $wpdb;
	$digi_sessions = (array) $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'digitalisimo\\_elements\\_kit\\_session\\_%'" );
	if ( ! $digi_sessions ) {
		$digi_fail( 'STATE=kit: «wp digitalisimo-elements kit import» no dejó ninguna sesión de K' );
		return;
	}
	$digi_session = substr( (string) end( $digi_sessions ), strlen( 'digitalisimo_elements_kit_session_' ) );
	$digi_pages   = array();
	// Tipos explícitos: `any` deja fuera los que tienen exclude_from_search, como la biblioteca de plantillas, que es
	// todo lo que trae un Template Kit.
	foreach ( get_posts( array( 'post_type' => array_values( array_intersect( array( 'page', 'post', 'elementor_library', 'e-landing-page' ), get_post_types() ) ), 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'meta_key' => '_elementor_import_session_id' ) ) as $digi_post ) {
		$digi_pages[] = array(
			'id'      => $digi_post->ID,
			'type'    => $digi_post->post_type,
			'status'  => $digi_post->post_status,
			'url'     => 'publish' === $digi_post->post_status ? get_permalink( $digi_post ) : get_preview_post_link( $digi_post ),
			'editor'  => in_array( $digi_post->post_type, array( 'page', 'elementor_library' ), true ),
		);
	}
	if ( ! $digi_pages ) {
		$digi_fail( 'STATE=kit: la importación no creó documentos' );
	}
	file_put_contents( $digi_dir . '/kit.json', wp_json_encode( array( 'session' => $digi_session, 'screen' => admin_url( 'admin.php?page=digitalisimo-elements-kits&session=' . rawurlencode( $digi_session ) ), 'pages' => $digi_pages ) ) );
	return;
}
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
// Registro del servidor (peticiones, redirecciones y correos hacia los plugins de origen). Se carga ya, antes de
// activar el constructor, para registrar también lo que pide la activación.
file_put_contents( WPMU_PLUGIN_DIR . '/digi-http-probe.php', '<?php require "/wordpress/wp-content/probe/probe-http.php";' );
require $digi_dir . '/probe-http.php';
// La auditoría recorre cada pantalla en español y en inglés sin cambiar el idioma del sitio.
file_put_contents( WPMU_PLUGIN_DIR . '/digi-locale.php', '<?php foreach ( array( "locale", "determine_locale" ) as $digi_hook ) { add_filter( $digi_hook, static function ( $locale ) { $c = $_COOKIE["digi_locale"] ?? ""; return preg_match( "/^[a-z]{2}_[A-Z]{2}$/", $c ) ? $c : $locale; }, 99 ); } add_filter( "get_user_metadata", static function ( $value, $id, $key ) { return "locale" === $key && ! empty( $_COOKIE["digi_locale"] ) ? array( $_COOKIE["digi_locale"] ) : $value; }, 10, 3 );' );
file_put_contents( WPMU_PLUGIN_DIR . '/digi-fatal-log.php', '<?php register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "" ) . "\n" . $e["message"] . " @ " . $e["file"] . ":" . $e["line"] . "\n\n", FILE_APPEND ); } } );' );

// ORIGINAL=1: Elementor de wordpress.org ya lo instaló el blueprint (inactivo); Elementor Pro sólo con sus cabeceras.
if ( ! empty( $digi_opts['original'] ) ) {
	if ( ! is_dir( WP_PLUGIN_DIR . '/elementor-pro' ) ) {
		mkdir( WP_PLUGIN_DIR . '/elementor-pro', 0777, true );
	}
	file_put_contents( WP_PLUGIN_DIR . '/elementor-pro/elementor-pro.php', "<?php\n/**\n * Plugin Name: Elementor Pro\n * Description: Elevate your designs and unlock the full power of Elementor.\n * Plugin URI: https://go.elementor.com/wp-dash-wp-plugins-author-uri/\n * Version: 3.30.0\n * Author: Elementor.com\n * Author URI: https://go.elementor.com/wp-dash-wp-plugins-author-uri/\n * Text Domain: elementor-pro\n */\n" );
	if ( ! file_exists( WP_PLUGIN_DIR . '/elementor/elementor.php' ) ) {
		$digi_fail( 'ORIGINAL=1: el blueprint no instaló el plugin Elementor' );
	}
}

activate_plugin( 'digitalisimo-elements/pro-elements.php' );
// SCOPE=full: DIGITALÍSIMO Migración (montado por brand-audit.sh) para revisar su pantalla.
if ( 'full' === ( $digi_opts['scope'] ?? 'full' ) && file_exists( WP_PLUGIN_DIR . '/digitalisimo-migration/digitalisimo-migration.php' ) ) {
	activate_plugin( 'digitalisimo-migration/digitalisimo-migration.php' );
}
update_option( 'elementor_element_cache_ttl', 'disable' );

$digi_doc = static function ( $post_type, $title, $slug, $type, array $data, array $extra = array() ) {
	$existing = get_page_by_path( $slug, OBJECT, $post_type );
	if ( $existing ) {
		return $existing->ID;
	}
	$id = wp_insert_post( array( 'post_type' => $post_type, 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', $type );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	if ( 'elementor_library' === $post_type ) {
		wp_set_object_terms( $id, $type, 'elementor_library_type' );
	}
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	foreach ( $extra as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return $id;
};
// Página de prueba. Los vídeos van sin URL: Elementor no guarda los valores por defecto, así que se pintan con el
// vídeo de muestra del proveedor (lo mismo que ven las páginas publicadas que nunca cambiaron ese ajuste).
$digi_ids = array();
$digi_ids['page'] = $digi_doc( 'page', 'Prueba de marca', 'digi-marca', 'wp-page', array(
	array( 'id' => 'mc00001', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 'mw00001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Título de prueba' ), 'elements' => array() ),
		array( 'id' => 'mw00002', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Botón de prueba' ), 'elements' => array() ),
		array( 'id' => 'mw00003', 'elType' => 'widget', 'widgetType' => 'video', 'settings' => array(), 'elements' => array() ),
		array( 'id' => 'mw00004', 'elType' => 'widget', 'widgetType' => 'e-youtube', 'settings' => array(), 'elements' => array(), 'version' => '0.4' ),
	) ),
	array( 'id' => 'mc00002', 'elType' => 'container', 'settings' => array( 'background_background' => 'video', 'min_height' => array( 'unit' => 'px', 'size' => 200 ) ), 'elements' => array(
		array( 'id' => 'mw00005', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Fondo de vídeo' ), 'elements' => array() ),
	) ),
) );
$digi_ids['template'] = $digi_doc( 'elementor_library', 'Plantilla de prueba', 'digi-marca-plantilla', 'section', array( array( 'id' => 'tc00001', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'tw00001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Contenido de plantilla' ), 'elements' => array() ) ) ) ) );
$digi_ids['popup'] = $digi_doc( 'elementor_library', 'Popup de prueba', 'digi-marca-popup', 'popup', array( array( 'id' => 'pc00001', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'pw00001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Contenido del popup' ), 'elements' => array() ) ) ) ) );
$digi_ids['header'] = $digi_doc( 'elementor_library', 'Cabecera de prueba', 'digi-marca-cabecera', 'header', array( array( 'id' => 'hc00001', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'hw00001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Cabecera' ), 'elements' => array() ) ) ) ) );
file_put_contents( $digi_dir . '/ids.json', wp_json_encode( $digi_ids ) );

// STATE=migrado: un sitio que viene de Elementor, con telemetría, beta, cuenta conectada y cachés remotas guardadas.
if ( 'migrado' === ( $digi_opts['state'] ?? 'nuevo' ) ) {
	update_option( 'elementor_allow_tracking', 'yes' );
	update_option( 'elementor_tracker_notice', '1' );
	update_option( '_elementor_installed_time', time() - 60 * DAY_IN_SECONDS );
	update_option( 'elementor_beta', 'yes' );
	update_option( 'elementor_connect_site_key', md5( 'digi-marca' ) );
	$admin = get_user_by( 'login', 'admin' );
	if ( $admin ) {
		$account = array( 'access_token' => 'digi-falso', 'access_token_secret' => 'digi-falso', 'client_id' => 'digi-falso', 'auth_secret' => 'digi-falso', 'user' => (object) array( 'email' => 'admin@localhost.test' ) );
		update_user_option( $admin->ID, 'elementor_connect_common_data', $account );
		update_user_option( $admin->ID, 'elementor_connect_library', $account );
		update_user_option( $admin->ID, 'elementor_connect_activate', $account );
	}
	$caches = json_decode( (string) @file_get_contents( $digi_dir . '/migrado.json' ), true );
	$sample = array( array( 'title' => 'Ejemplo del proveedor', 'url' => 'https://go.elementor.com/digi-marca-ejemplo/', 'image' => 'https://assets.elementor.com/digi-marca/ejemplo.png', 'youtube_embed_id' => 'XHOmBV4js_E' ) );
	foreach ( (array) ( $caches['caches'] ?? array() ) as $cache ) {
		$raw  = json_decode( (string) @file_get_contents( $digi_dir . '/migrado/' . $cache['option'] . '.json' ), true );
		$data = is_array( $raw ) && ! empty( $cache['key'] ) ? ( $raw[ $cache['key'] ] ?? null ) : $raw;
		if ( empty( $data ) || ! is_array( $data ) ) {
			$data = $sample;
			file_put_contents( $digi_dir . '/setup-warnings.txt', 'STATE=migrado: sin datos descargados para ' . $cache['option'] . ", se usa un ejemplo\n", FILE_APPEND );
		}
		if ( 'assets' === $cache['format'] ) {
			update_option( $cache['option'], array( 'timeout' => time() + YEAR_IN_SECONDS, 'value' => wp_json_encode( $data ) ), false );
		} else {
			update_option( $cache['option'], $data, false );
		}
	}
}

update_option( 'digi_brand_core', defined( 'DIGITALISIMO_ELEMENTS_CORE' ) ? 'bundled' : 'plugin' );
if ( ! is_plugin_active( 'digitalisimo-elements/pro-elements.php' ) || ! defined( 'DIGITALISIMO_ELEMENTS_CORE' ) ) {
	$digi_fail( 'DIGITALÍSIMO Elements no quedó activo con su núcleo incluido' );
}
