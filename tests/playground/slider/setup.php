<?php
/**
 * Página para la prueba del Slider Optimizado en navegador: cuatro instancias en una misma página (onda de diez copias
 * de una imagen en modo múltiple con espejo, doce logotipos distintos, onda repetida con espejo y, tras un separador de
 * 3000 px, otra de logotipos lejana), con imágenes PNG reales creadas aquí como adjuntos.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
$fail = static function ( $message ) { file_put_contents( '/wordpress/wp-content/probe/setup-error.txt', $message . "\n", FILE_APPEND ); };
$png  = array( 'wave' => 'iVBORw0KGgoAAAANSUhEUgAABkAAAAEsCAIAAAAU/+hfAAAHWElEQVR42u3YQQ0AAAjEsJODJhQjCxskNKmCPZfqAQAAAICzIgEAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAgIEFAAAAgIEFAAAAAAYWAAAAAAYWAAAAABhYAAAAAGBgAQAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAYWAAAAAAYWAAAAABgYAEAAABgYAEAAACAgQUAAAAABhYAAAAABhYAAAAAGFgAAAAAGFgqAAAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAYWAAAAAAYWAAAAABgYAEAAABgYAEAAACAgQUAAAAABhYAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAgIEFAAAAgIEFAAAAAAYWAAAAAAYWAAAAABhYAAAAAGBgAQAAAGBgAQAAAICBBQAAAICBpQIAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAgIEFAAAAgIEFAAAAAAYWAAAAAAYWAAAAABhYAAAAAGBgAQAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAYWAAAAAAYWAAAAABgYAEAAABgYAEAAACAgQUAAAAABhYAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAgIEFAAAAgIEFAAAAAAYWAAAAABhYAAAAABhYAAAAAGBgAQAAAGBgAQAAAICBBQAAAAAGFgAAAAAGFgAAAAAYWAAAAAAYWAAAAABgYAEAAACAgQUAAACAgQUAAAAABhYAAAAABhYAAAAAGFgAAAAAYGABAAAAYGABAAAAgIEFAAAAgIEFAAAAAAYWAAAAABhYAAAAABhYAAAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAGFgAAAAAYWAAAAABgYAEAAABgYAEAAACAgQUAAACAgQUAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAYGABAAAAgIEFAAAAAAYWAAAAAAYWAAAAABhYAAAAABhYAAAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAYWAAAAAAYWAAAAABgYAEAAABgYAEAAACAgQUAAAAABhYAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAgIEFAAAAgIEFAAAAAAYWAAAAAAYWAAAAABhYAAAAAGBgAQAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAYWAAAAAAYWAAAAABgYAEAAACAgQUAAACAgQUAAAAABhYAAAAABhYAAAAAGFgAAAAAYGABAAAAYGABAAAAgIEFAAAAgIEFAAAAAAYWAAAAABhYAAAAABhYAAAAAGBgAQAAAGBgAQAAAICBBQAAAAAGFgAAAAAGFgAAAAAYWAAAAAAYWAAAAABgYAEAAACAgQUAAACAgQUAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAYGABAAAAgIEFAAAAAAYWAAAAAAYWAAAAABhYAAAAABhYAAAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAGFgAAAAAYWAAAAABgYAEAAABgYAEAAACAgQUAAACAgQUAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAgIEFAAAAgIEFAAAAAAYWAAAAAAYWAAAAABhYAAAAAGBgAQAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAYWAAAAAAYWAAAAABgYAEAAABgYAEAAACAgQUAAAAABhYAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAgIEFAAAAgIEFAAAAAAYWAAAAABhYAAAAABhYAAAAAGBgAQAAAGBgAQAAAICBBQAAAAAGFgAAAAAGFgAAAAAYWAAAAAAYWAAAAABgYAEAAACAgQUAAACAgQUAAAAABhYAAAAABhYAAAAAGFgAAAAAYGABAAAAYGABAAAAgIEFAAAAgIEFAAAAAAYWAAAAABhYAAAAABhYAAAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAGFgAAAAAYWAAAAABgYAEAAABgYAEAAACAgQUAAACAgQUAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAYGABAAAAgIEFAAAAAAYWAAAAAAYWAAAAABhYAAAAABhYAAAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAGlgQAAAAAGFgAAAAAYGABAAAAYGABAAAAgIEFAAAAAAYWAAAAAAYWAAAAABhYAAAAABhYAAAAAGBgAQAAAICBBQAAAICBBQAAAAAGFgAAAAAGFgAAAAAYWAAAAABgYAEAAABgYAEAAACAgQUAAACAgQUAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAYGCpAAAAAICBBQAAAAAGFgAAAAAGFgAAAAAYWAAAAABgYAEAAABgYAEAAACAgQUAAACAgQUAAAAABhYAAAAAGFgAAAAAGFgAAAAAYGABAAAAYGABAAAAgIEFAAAAAAYWAAAAAAYWAAAAABhYAAAAAHy05E2LoGP8lSIAAAAASUVORK5CYII=', 'logo' => 'iVBORw0KGgoAAAANSUhEUgAAAPAAAAB4CAIAAABD1OhwAAAA+UlEQVR42u3SAQkAAAzDsAmbfy7rNs4JREFppoU3IgGGBkODocHQGBoMDYYGQ4OhMTQYGgwNhgZDY2gwNBgaDA2GxtBgaDA0GBoMjaHB0GBoMDQYGkODocHQYGgwNIYGQ4OhwdAYGgwNhgZDg6ExNBgaDA2GBkNjaDA0GBoMDYbG0GBoMDQYGgyNocHQYGgwNBgaQ4OhwdBgaDA0hgZDg6HB0GBoDA2GBkODoTE0GBoMDYYGQ2NoMDQYGgwNhsbQYGgwNBgaDI2hwdBgaDA0GBpDg6HB0GBoMDSGBkODocHQYGgMDYYGQ4OhMbQKGBoMDYYGQ2NoMDTcshCrqDVF+sl3AAAAAElFTkSuQmCC' );
$attachment = static function ( $name, $index ) use ( $png, $fail ) {
	$upload = wp_upload_bits( $name . '-' . $index . '.png', null, base64_decode( $png[ $name ] ) );
	if ( ! empty( $upload['error'] ) ) { $fail( 'No se pudo subir ' . $name . ': ' . $upload['error'] ); return array( 'id' => 0, 'url' => '' ); }
	$id   = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $name . ' ' . $index, 'post_status' => 'inherit' ), $upload['file'] );
	$meta = wp_generate_attachment_metadata( $id, $upload['file'] );
	if ( ! is_array( $meta ) || empty( $meta['width'] ) ) {
		$size = wp_getimagesize( $upload['file'] );
		$meta = array( 'width' => (int) $size[0], 'height' => (int) $size[1], 'file' => _wp_relative_upload_path( $upload['file'] ), 'sizes' => array() );
	}
	wp_update_attachment_metadata( $id, $meta );
	return array( 'id' => (int) $id, 'url' => $upload['url'] );
};
$wave  = $attachment( 'wave', 0 );
$logos = array();
for ( $i = 1; $i <= 12; $i++ ) $logos[] = $attachment( 'logo', $i );
$px     = static function ( $size ) { return array( 'size' => $size, 'unit' => 'px', 'sizes' => array() ); };
$widget = static function ( $id, array $settings ) { return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'digitalisimo-slider-optimizado', 'settings' => (object) $settings, 'elements' => array() ); };
$logo_settings = array( 'mode' => 'multiple', 'gallery_images' => $logos, 'animation' => 'continuous', 'infinite' => 'yes', 'speed' => 100, 'visible' => 9, 'visible_tablet' => 6, 'visible_mobile' => 3, 'image_size' => 'full' );
$elements = array( array( 'id' => 'sldc01', 'elType' => 'container', 'settings' => (object) array( 'content_width' => 'full' ), 'elements' => array(
	$widget( 'sldw01', array( 'mode' => 'multiple', 'gallery_images' => array_fill( 0, 10, $wave ), 'mirror' => 'yes', 'animation' => 'continuous', 'infinite' => 'yes', 'direction' => 'right', 'speed' => 75, 'visible' => 1, 'visible_tablet' => 6, 'visible_mobile' => 1, 'gap' => $px( 0 ), 'image_size' => 'full', 'label' => 'Onda' ) ),
	$widget( 'sldw02', $logo_settings + array( 'label' => 'Logos' ) ),
	$widget( 'sldw03', array( 'mode' => 'repeat', 'repeat_image' => $wave, 'repeat_count' => 10, 'mirror' => 'yes', 'animation' => 'continuous', 'infinite' => 'yes', 'speed' => 30, 'image_size' => 'full', 'label' => 'Onda repetida' ) ),
	array( 'id' => 'sldsp1', 'elType' => 'widget', 'widgetType' => 'spacer', 'settings' => (object) array( 'space' => $px( 3000 ) ), 'elements' => array() ),
	$widget( 'sldw04', $logo_settings + array( 'label' => 'Logos lejanos' ) ),
) ) );
$slug = 'digi-slider';
$page = get_page_by_path( $slug );
$id   = $page ? $page->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
update_post_meta( $id, '_elementor_edit_mode', 'builder' );
update_post_meta( $id, '_elementor_template_type', 'wp-page' );
update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
update_option( 'elementor_onboarded', true );
update_option( 'elementor_element_cache_ttl', 'disable' );
if ( ! $wave['id'] || count( array_filter( array_column( $logos, 'id' ) ) ) !== 12 ) $fail( 'Faltan adjuntos' );
if ( ! is_plugin_active( 'digitalisimo-elements/pro-elements.php' ) ) $fail( 'digitalisimo-elements no quedó activo' );
