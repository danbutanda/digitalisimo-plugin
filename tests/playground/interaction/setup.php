<?php
/**
 * Páginas para la prueba de interacción en navegador (sitio simple, sin Element Pack): una página
 * `digi-ix-<widget>` por caso, con los ajustes por defecto del widget heredado.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
activate_plugin( 'elementor/elementor.php' );
activate_plugin( 'digitalisimo-elements/pro-elements.php' );
update_option( 'elementor_element_cache_ttl', 'disable' );
update_option( 'elementor_onboarded', true );
// «__SITE__» en cualquier valor es la URL del sitio (imágenes de ejemplo).
$cases = json_decode( str_replace( '__SITE__', untrailingslashit( home_url() ), (string) file_get_contents( __DIR__ . '/cases.json' ) ), true );
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
