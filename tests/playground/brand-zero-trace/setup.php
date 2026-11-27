<?php
/**
 * Preparación de la auditoría de cero rastro (paso runPHP del blueprint de brand-zero-trace.sh): activa DIGITALÍSIMO
 * Elements con el MCP del sitio, crea la página `digi-zt` (encabezado, botón, carrusel de imágenes con lightbox, lista de
 * iconos y formulario), enciende la marca propia si MARCA=on (/digi-probe/options.json), instala el prototipo de la capa
 * del front si PROTO=1 y deja /digi-probe/setup.json con el ID de la página y la contraseña de aplicación en
 * /digi-probe/secrets.json (se borra al terminar).
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = '/digi-probe';
try {
	$opts = json_decode( (string) file_get_contents( $dir . '/options.json' ), true ) ?: array();
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-zt-probe.php' );
	if ( ! empty( $opts['proto'] ) ) {
		copy( $dir . '/proto.php', WPMU_PLUGIN_DIR . '/digi-zt-proto.php' );
		copy( $dir . '/buffer.php', WPMU_PLUGIN_DIR . '/digi-zt-buffer.inc' );
	}
	$activated = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
	if ( is_wp_error( $activated ) ) {
		throw new RuntimeException( 'No se activó Elements: ' . $activated->get_error_message() );
	}
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'permalink_structure', '' );
	update_option( 'WPLANG', 'es_MX' );
	if ( 'on' === ( $opts['marca'] ?? 'off' ) ) {
		update_option( 'digitalisimo_elements_marca', array( 'mode' => 'on', 'force' => true ) );
	}
	// Una imagen de la biblioteca con «elementor» en el nombre: la capa nunca debe tocar URL de medios.
	$upload = wp_upload_bits( 'elementor-banner.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) );
	$img    = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'banner', 'post_status' => 'inherit' ), $upload['file'] );
	$url    = wp_get_attachment_url( $img );
	$data   = array(
		array( 'id' => 'c0ffee1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			array( 'id' => 'h0000a1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Prueba de marca: guía de Elementor' ), 'elements' => array() ),
			array( 'id' => 'b0000a2', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Comprar', 'link' => array( 'url' => '#' ) ), 'elements' => array() ),
			array( 'id' => 'c0000a3', 'elType' => 'widget', 'widgetType' => 'image-carousel', 'settings' => array( 'carousel' => array( array( 'id' => $img, 'url' => $url ), array( 'id' => $img, 'url' => $url ) ), 'link_to' => 'file', 'open_lightbox' => 'yes' ), 'elements' => array() ),
			array( 'id' => 'i0000a4', 'elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => array( 'icon_list' => array( array( 'text' => 'Uno', '_id' => 'aa1' ) ) ), 'elements' => array() ),
			array( 'id' => 'f0000a5', 'elType' => 'widget', 'widgetType' => 'form', 'settings' => array( 'form_name' => 'Contacto' ), 'elements' => array() ),
		) ),
	);
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'digi-zt', 'post_name' => 'digi-zt' ) );
	update_post_meta( $page, '_elementor_edit_mode', 'builder' );
	update_post_meta( $page, '_elementor_template_type', 'wp-page' );
	update_post_meta( $page, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.3.5' );
	update_post_meta( $page, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	// Un borrador para publicarlo desde el editor.
	$draft = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'digi-zt-borrador', 'post_name' => 'digi-zt-borrador' ) );
	update_post_meta( $draft, '_elementor_edit_mode', 'builder' );
	update_post_meta( $draft, '_elementor_template_type', 'wp-page' );
	update_post_meta( $draft, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.3.5' );
	update_post_meta( $draft, '_elementor_data', wp_slash( wp_json_encode( array( $data[0] ) ) ) );
	update_post_meta( $draft, '_wp_page_template', 'elementor_canvas' );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $page );
	$admin = get_user_by( 'login', 'admin' );
	add_filter( 'wp_is_application_passwords_available', '__return_true' );
	$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-zt' ) );
	if ( is_wp_error( $created ) ) {
		throw new RuntimeException( 'Sin contraseña de aplicación: ' . $created->get_error_message() );
	}
	file_put_contents( $dir . '/secrets.json', wp_json_encode( array( 'password' => $created[0] ) ) );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'page' => $page, 'draft' => $draft, 'image' => $url, 'core' => class_exists( 'Digitalisimo_Elements_Core' ) ? Digitalisimo_Elements_Core::dir() : '', 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
