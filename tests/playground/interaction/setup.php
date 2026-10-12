<?php
/**
 * Páginas para la prueba de interacción en navegador (sitio simple, sin Element Pack): una página
 * `digi-ix-<widget>` por caso, con los ajustes por defecto del widget heredado.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
// Con CORE=bundled el plugin Elementor no está instalado: DIGITALÍSIMO Elements trae su núcleo.
if ( file_exists( WP_PLUGIN_DIR . '/elementor/elementor.php' ) ) {
	activate_plugin( 'elementor/elementor.php' );
}
// Registro de errores fatales de cualquier petición (incluidas las AJAX del editor) en la carpeta de la prueba.
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
file_put_contents( WPMU_PLUGIN_DIR . '/digi-fatal-log.php', '<?php register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "" ) . " " . ( $_POST["action"] ?? "" ) . " " . substr( (string) ( $_POST["actions"] ?? "" ), 0, 300 ) . "\n" . $e["message"] . " @ " . $e["file"] . ":" . $e["line"] . "\n\n", FILE_APPEND ); } } );' );
activate_plugin( 'digitalisimo-elements/pro-elements.php' );
activate_plugin( 'digitalisimo-migration/digitalisimo-migration.php' );
update_option( 'elementor_element_cache_ttl', 'disable' );
update_option( 'elementor_onboarded', true );
// Productos de prueba para el carrusel de WooCommerce.
if ( class_exists( 'WC_Product_Simple' ) && ! get_posts( array( 'post_type' => 'product', 'posts_per_page' => 1, 'fields' => 'ids' ) ) ) {
	foreach ( array( 'Camisa', 'Gorra', 'Taza', 'Libro', 'Bolsa', 'Lámpara' ) as $i => $name ) {
		$product = new WC_Product_Simple();
		$product->set_name( $name . ' IX' );
		$product->set_status( 'publish' );
		$product->set_regular_price( (string) ( 10 + $i ) );
		$product->save();
	}
}
// Plantilla de Elementor para los casos que muestran una plantilla («__TEMPLATE__»).
if ( ! get_page_by_path( 'digi-ab-template', OBJECT, 'elementor_library' ) ) {
	$template = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'digi-ab-template', 'post_name' => 'digi-ab-template' ) );
	update_post_meta( $template, '_elementor_edit_mode', 'builder' );
	update_post_meta( $template, '_elementor_template_type', 'section' );
	update_post_meta( $template, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'tplsec1', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'tpl0001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Contenido de plantilla' ), 'elements' => array() ) ) ) ) ) ) );
}
$template = get_page_by_path( 'digi-ab-template', OBJECT, 'elementor_library' );
// «__SITE__» en cualquier valor es la URL del sitio (imágenes de ejemplo); «__TEMPLATE__», el ID de la plantilla.
$cases = json_decode( str_replace( array( '__SITE__', '__TEMPLATE__' ), array( untrailingslashit( home_url() ), (string) $template->ID ), (string) file_get_contents( __DIR__ . '/cases.json' ) ), true );
foreach ( $cases as $widget => $settings ) {
	$slug = 'digi-ix-' . $widget;
	if ( get_page_by_path( $slug ) ) {
		continue;
	}
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 's' . substr( md5( $slug ), 0, 6 ), 'elType' => 'section', 'settings' => (object) array(), 'elements' => array( array( 'id' => 'c' . substr( md5( $slug ), 0, 6 ), 'elType' => 'column', 'settings' => array( '_column_size' => 100 ), 'elements' => array(
		array( 'id' => 'w' . substr( md5( $slug ), 0, 6 ), 'elType' => 'widget', 'widgetType' => $widget, 'settings' => (object) $settings, 'elements' => array() ),
	) ) ) ) ) ) ) );
}
update_option( 'digi_ix_ready', count( $cases ) );
update_option( 'digi_ix_core', defined( 'DIGITALISIMO_ELEMENTS_CORE' ) ? 'bundled' : 'plugin' );
// Sin estos dos plugins activos no hay nada que probar: se avisa en vez de fallar caso por caso.
foreach ( array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php' ) as $required ) {
	if ( ! is_plugin_active( $required ) ) {
		file_put_contents( '/wordpress/wp-content/probe/setup-error.txt', $required . ' no quedó activo' . "\n", FILE_APPEND );
	}
}
