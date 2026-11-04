<?php
/**
 * Datos de pro-loop.sh (C5, frente J), en una petición propia con el constructor cargado: 9 entradas deterministas
 * (categorías «Noticias», «Guías» y «Casos», etiquetas, extracto e imagen destacada) y dos plantillas de Elemento de
 * bucle (tarjeta y alterna). Deja sus IDs en `fixtures.json`.
 */
require '/wordpress/wp-load.php';

const DIGI_LOOP_PROBE = '/wordpress/wp-content/probe';

function digi_loop_fail( $message ) {
	file_put_contents( DIGI_LOOP_PROBE . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

function digi_loop_image( $n ) {
	$im = imagecreatetruecolor( 600, 400 );
	imagefill( $im, 0, 0, imagecolorallocate( $im, ( 40 * $n ) % 255, ( 90 + 20 * $n ) % 255, 200 ) );
	ob_start();
	imagepng( $im );
	$png = (string) ob_get_clean();
	imagedestroy( $im );
	$upload = wp_upload_bits( "pro-loop-$n.png", null, $png );
	$id     = (int) wp_insert_attachment( array( 'post_title' => "Imagen $n", 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $upload['file'] );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', "Imagen de la entrada $n" );
	return $id;
}

function digi_loop_tag( $name ) {
	return '[elementor-tag id="' . substr( md5( $name ), 0, 7 ) . '" name="' . $name . '" settings="%7B%7D"]';
}

function digi_loop_template( $slug, $color ) {
	$tree = array(
		array(
			'id'       => substr( md5( $slug . 'box' ), 0, 7 ),
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => array( 'flex_direction' => 'column', 'background_background' => 'classic', 'background_color' => $color ),
			'elements' => array(
				array( 'id' => substr( md5( $slug . 'img' ), 0, 7 ), 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( '__dynamic__' => array( 'image' => digi_loop_tag( 'post-featured-image' ) ) ), 'elements' => array() ),
				array( 'id' => substr( md5( $slug . 'h' ), 0, 7 ), 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'T', '__dynamic__' => array( 'title' => digi_loop_tag( 'post-title' ), 'link' => digi_loop_tag( 'post-url' ) ) ), 'elements' => array() ),
			),
		),
	);
	$id = (int) wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
	wp_set_object_terms( $id, 'loop-item', 'elementor_library_type' );
	update_post_meta( $id, '_elementor_template_type', 'loop-item' );
	update_post_meta( $id, '_elementor_source', 'post' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
	return $id;
}

if ( ! class_exists( '\Elementor\Plugin' ) ) {
	digi_loop_fail( 'El constructor no está cargado' );
	return;
}
wp_delete_post( 1, true ); // «¡Hola, mundo!»: las cuentas de las pruebas son exactas.
$cats = array();
foreach ( array( 'Noticias', 'Guías', 'Casos' ) as $name ) {
	$cats[] = (int) wp_insert_term( $name, 'category' )['term_id'];
}
$posts = array();
for ( $i = 1; $i <= 9; $i++ ) {
	$id = (int) wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => "Entrada de prueba $i",
			'post_name'     => "entrada-de-prueba-$i",
			'post_excerpt'  => "Extracto de la entrada $i.",
			'post_content'  => "Contenido de la entrada $i.",
			'post_date'     => sprintf( '2026-01-%02d 10:00:00', $i ),
			'post_category' => array( $cats[ ( $i - 1 ) % 3 ] ),
			'tags_input'    => array( 0 === $i % 2 ? 'par' : 'impar' ),
		)
	);
	set_post_thumbnail( $id, digi_loop_image( $i ) );
	$posts[] = $id;
}
$out = array(
	'posts'     => $posts,
	'cats'      => $cats,
	'card'      => digi_loop_template( 'pro-loop-card', '#f1f3f5' ),
	'alt'       => digi_loop_template( 'pro-loop-alt', '#fdebd0' ),
);
file_put_contents( DIGI_LOOP_PROBE . '/fixtures.json', wp_json_encode( $out ) );
