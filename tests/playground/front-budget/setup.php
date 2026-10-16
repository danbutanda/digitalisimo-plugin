<?php
/**
 * Páginas para la medición del front: una por caso de `cases.json` (`digi-fb-<caso>`), con el widget indicado y sus
 * ajustes. «__SITE__» es la URL del sitio y «__IMAGE__» el adjunto de prueba (un PNG subido a Medios para que las
 * imágenes salgan con srcset y dimensiones, como en un sitio real).
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
file_put_contents( WPMU_PLUGIN_DIR . '/digi-fatal-log.php', '<?php register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "" ) . " " . $e["message"] . " en " . $e["file"] . ":" . $e["line"] . "\n", FILE_APPEND ); } } );' );
activate_plugin( 'digitalisimo-elements/pro-elements.php' );
update_option( 'elementor_element_cache_ttl', 'disable' );
update_option( 'elementor_onboarded', true );
update_option( 'users_can_register', 1 );
update_option( 'blogdescription', 'Medición del front' );

// Adjunto de prueba: el marcador de Elementor copiado a Medios, con sus tamaños intermedios si el servidor puede generarlos.
$image_id = (int) get_option( 'digi_fb_image' );
if ( ! $image_id || ! get_post( $image_id ) ) {
	$source = WP_PLUGIN_DIR . '/digitalisimo-elements/elementor/assets/images/placeholder.png';
	$upload = wp_upload_bits( 'digi-medicion.png', null, (string) file_get_contents( $source ) );
	if ( empty( $upload['error'] ) ) {
		$image_id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Imagen de medición', 'post_status' => 'inherit' ), $upload['file'] );
		update_post_meta( $image_id, '_wp_attachment_image_alt', 'Imagen de medición' );
		$metadata = wp_generate_attachment_metadata( $image_id, $upload['file'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $image_id, $metadata );
		}
		update_option( 'digi_fb_image', $image_id );
	} else {
		file_put_contents( '/wordpress/wp-content/probe/setup-error.txt', 'No se pudo subir la imagen de prueba: ' . $upload['error'] . "\n", FILE_APPEND );
	}
}
$image_url = $image_id ? (string) wp_get_attachment_url( $image_id ) : home_url( '/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png' );
$raw = (string) file_get_contents( __DIR__ . '/cases.json' );
$raw = str_replace( array( '"__IMAGE__"', '__SITE__' ), array( wp_json_encode( array( 'id' => $image_id, 'url' => $image_url ) ), untrailingslashit( home_url() ) ), $raw );
$cases = json_decode( $raw, true );
if ( ! is_array( $cases ) ) {
	file_put_contents( '/wordpress/wp-content/probe/setup-error.txt', 'cases.json no es JSON válido' . "\n", FILE_APPEND );
	$cases = array();
}
foreach ( $cases as $slug => $case ) {
	$page = 'digi-fb-' . $slug;
	if ( get_page_by_path( $page ) ) {
		continue;
	}
	// El mismo encabezado de referencia en todas las páginas: la fuente del kit no es coste del widget medido.
	$widgets = array( array( 'id' => 'h' . substr( md5( $page ), 0, 6 ), 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => (object) array( 'title' => 'Página de medición' ), 'elements' => array() ) );
	foreach ( (array) $case['widgets'] as $index => $widget ) {
		if ( 'empty' === $slug ) { break; }
		$widgets[] = array( 'id' => 'w' . substr( md5( $page . $index ), 0, 6 ), 'elType' => 'widget', 'widgetType' => (string) $widget['type'], 'settings' => (object) ( $widget['settings'] ?? array() ), 'elements' => array() );
	}
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $page, 'post_name' => $page, 'post_content' => str_repeat( '<p>Texto de la página para medir el tiempo de lectura y el progreso.</p>', 40 ) ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c' . substr( md5( $page ), 0, 6 ), 'elType' => 'container', 'settings' => (object) array(), 'elements' => $widgets ) ) ) ) );
}
update_option( 'digi_fb_ready', count( $cases ) );
if ( ! is_plugin_active( 'digitalisimo-elements/pro-elements.php' ) ) {
	file_put_contents( '/wordpress/wp-content/probe/setup-error.txt', 'digitalisimo-elements no quedó activo' . "\n", FILE_APPEND );
}
