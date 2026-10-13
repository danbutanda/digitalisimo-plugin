<?php
/**
 * Sitio para la auditoría de marca: DIGITALÍSIMO Elements con su núcleo incluido (sin el plugin Elementor),
 * una página construida con el editor, una plantilla y un selector de idioma por cookie (`digi_locale`).
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
// La auditoría recorre cada pantalla en español y en inglés sin cambiar el idioma del sitio.
file_put_contents( WPMU_PLUGIN_DIR . '/digi-locale.php', '<?php foreach ( array( "locale", "determine_locale" ) as $digi_hook ) { add_filter( $digi_hook, static function ( $locale ) { $c = $_COOKIE["digi_locale"] ?? ""; return preg_match( "/^[a-z]{2}_[A-Z]{2}$/", $c ) ? $c : $locale; }, 99 ); } add_filter( "get_user_metadata", static function ( $value, $id, $key ) { return "locale" === $key && ! empty( $_COOKIE["digi_locale"] ) ? array( $_COOKIE["digi_locale"] ) : $value; }, 10, 3 );' );
file_put_contents( WPMU_PLUGIN_DIR . '/digi-fatal-log.php', '<?php register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "" ) . "\n" . $e["message"] . " @ " . $e["file"] . ":" . $e["line"] . "\n\n", FILE_APPEND ); } } );' );
activate_plugin( 'digitalisimo-elements/pro-elements.php' );
update_option( 'elementor_onboarded', true );
update_option( 'elementor_element_cache_ttl', 'disable' );
if ( ! get_page_by_path( 'digi-marca' ) ) {
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Prueba de marca', 'post_name' => 'digi-marca' ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'mc00001', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 'mw00001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Título de prueba' ), 'elements' => array() ),
		array( 'id' => 'mw00002', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Botón de prueba' ), 'elements' => array() ),
	) ) ) ) ) );
}
if ( ! get_page_by_path( 'digi-marca-plantilla', OBJECT, 'elementor_library' ) ) {
	$template = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Plantilla de prueba', 'post_name' => 'digi-marca-plantilla' ) );
	update_post_meta( $template, '_elementor_edit_mode', 'builder' );
	update_post_meta( $template, '_elementor_template_type', 'section' );
	wp_set_object_terms( $template, 'section', 'elementor_library_type' );
	update_post_meta( $template, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'tc00001', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'tw00001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Contenido de plantilla' ), 'elements' => array() ) ) ) ) ) ) );
}
update_option( 'digi_brand_core', defined( 'DIGITALISIMO_ELEMENTS_CORE' ) ? 'bundled' : 'plugin' );
if ( ! is_plugin_active( 'digitalisimo-elements/pro-elements.php' ) || ! defined( 'DIGITALISIMO_ELEMENTS_CORE' ) ) {
	file_put_contents( '/wordpress/wp-content/probe/setup-error.txt', "DIGITALÍSIMO Elements no quedó activo con su núcleo incluido\n", FILE_APPEND );
}
