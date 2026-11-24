<?php
/**
 * Preparación del ensayo de los vídeos de fondo (paso runPHP del blueprint de elements-background-video.sh): activa
 * DIGITALÍSIMO Elements, sube un MP4 de 3 MB y una imagen de respaldo a la biblioteca y crea tres páginas con un
 * contenedor con fondo de vídeo: «sí» (Reproducir en móvil encendido, con imagen de respaldo), «no» (apagado, con imagen
 * de respaldo) y «sin-respaldo» (apagado, sin imagen). Deja /digi-bv/ready.json; un fallo, en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
try {
	$done = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
	if ( is_wp_error( $done ) ) {
		throw new RuntimeException( 'Elements: ' . $done->get_error_message() );
	}
	update_option( 'elementor_onboarded', true );
	update_option( 'permalink_structure', '' );
	$uploads = wp_upload_dir();
	wp_mkdir_p( $uploads['path'] );
	// Un MP4 de 3 MB (bytes al azar tras una cabecera ftyp: el navegador lo pide igual) y un PNG de respaldo.
	$video = $uploads['path'] . '/fondo-del-ensayo.mp4';
	file_put_contents( $video, hex2bin( '0000001866747970697336d00000000069736f6d69736f32' ) . random_bytes( 3 * 1048576 ) );
	$image = $uploads['path'] . '/respaldo-del-ensayo.png';
	file_put_contents( $image, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) );
	$ids = array();
	foreach ( array( 'video' => array( $video, 'video/mp4' ), 'image' => array( $image, 'image/png' ) ) as $key => $file ) {
		$ids[ $key ] = wp_insert_attachment( array( 'post_mime_type' => $file[1], 'post_title' => basename( $file[0] ), 'post_status' => 'inherit' ), $file[0] );
	}
	$video_url = wp_get_attachment_url( $ids['video'] );
	$image_url = wp_get_attachment_url( $ids['image'] );
	$pages     = array();
	foreach ( array( 'si' => array( 'yes', true ), 'no' => array( '', true ), 'sin-respaldo' => array( '', false ) ) as $slug => $case ) {
		$settings = array(
			'background_background'     => 'video',
			'background_video_link'     => $video_url,
			'background_play_on_mobile' => $case[0],
			'min_height'                => array( 'unit' => 'px', 'size' => 400, 'sizes' => array() ),
		);
		if ( $case[1] ) {
			$settings['background_video_fallback'] = array( 'id' => $ids['image'], 'url' => $image_url );
		}
		$data = array( array( 'id' => 'c' . substr( md5( $slug ), 0, 6 ), 'elType' => 'container', 'settings' => $settings, 'elements' => array( array( 'id' => 'w' . substr( md5( $slug ), 0, 6 ), 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Fondo ' . $slug ), 'elements' => array() ) ) ) );
		$id   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Fondo ' . $slug, 'post_name' => 'fondo-' . $slug ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
		$pages[ $slug ] = $id;
	}
	file_put_contents( '/digi-bv/ready.json', wp_json_encode( array( 'pages' => $pages, 'video' => $video_url, 'bytes' => filesize( $video ), 'wp' => $GLOBALS['wp_version'], 'php' => PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( '/digi-bv/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
