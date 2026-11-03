<?php
/**
 * Paso del blueprint de brand-audit.sh (F8, frente J), en otra petición con DIGITALÍSIMO Elements ya cargado: la página
 * `digi-marca-f8` con los rasgos nuevos de F8 (información emergente en un botón, distintivo en un encabezado y en su
 * contenedor) y el contador del slider anidado, guardada con `Document::save()` como administrador. Añade a `ids.json`
 * la clave `f8` (página). `ONLY=f8` recorre su editor con cada control nuevo seleccionado y su frontal.
 */
require '/wordpress/wp-load.php';
$digi_dir  = '/wordpress/wp-content/probe';
$digi_fail = static function ( $message ) use ( $digi_dir ) {
	file_put_contents( $digi_dir . '/setup-error.txt', 'F8: ' . $message . "\n", FILE_APPEND );
};
if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
	$digi_fail( 'el constructor no está cargado' );
	return;
}
wp_set_current_user( 1 );
$found = get_posts( array( 'post_type' => 'page', 'name' => 'digi-marca-f8', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
$page  = $found ? (int) $found[0] : (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Marca F8', 'post_name' => 'digi-marca-f8' ) );
update_post_meta( $page, '_elementor_edit_mode', 'builder' );
update_post_meta( $page, '_elementor_template_type', 'wp-page' );
$w      = static function ( $id, $type, array $settings, array $elements = array() ) {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => $elements );
};
$slides = array();
foreach ( array( 'uno', 'dos', 'tres' ) as $i => $n ) {
	$slides[] = array( 'id' => 'mf8s0' . $i, 'elType' => 'container', 'isInner' => true, 'settings' => array( 'content_width' => 'full' ), 'elements' => array( $w( 'mf8h0' . $i, 'heading', array( 'title' => 'Diapositiva ' . $n ) ) ) );
}
$document = \Elementor\Plugin::$instance->documents->get( $page, false );
if ( ! $document ) {
	$digi_fail( 'sin documento para la página' );
	return;
}
$document->save(
	array(
		'elements' => array(
			array(
				'id'       => 'mf8c000',
				'elType'   => 'container',
				'settings' => array( 'digitalisimo_badge' => 'yes', 'digitalisimo_badge_text' => 'Contenedor', 'digitalisimo_badge_corner' => 'bottom-end' ),
				'elements' => array(
					$w( 'mf8001', 'button', array( 'text' => 'Ayuda', 'link' => array( 'url' => '#' ), 'digitalisimo_tooltip' => 'yes', 'digitalisimo_tooltip_text' => 'Texto <strong>breve</strong>', 'digitalisimo_tooltip_placement' => 'top' ) ),
					$w( 'mf8002', 'heading', array( 'title' => 'Con distintivo', 'digitalisimo_badge' => 'yes', 'digitalisimo_badge_text' => 'Nuevo', 'digitalisimo_badge_corner' => 'top-start' ) ),
					$w( 'mf8003', 'digitalisimo-nested-slider', array( 'carousel_label' => 'Slider con contador', 'slides' => array( array( '_id' => 's1', 'slide_title' => 'Uno' ), array( '_id' => 's2', 'slide_title' => 'Dos' ), array( '_id' => 's3', 'slide_title' => 'Tres' ) ), 'navigation' => 'both', 'counter' => 'yes' ), $slides ),
				),
			),
		),
	)
);
$digi_ids       = json_decode( (string) @file_get_contents( $digi_dir . '/ids.json' ), true ) ?: array(); // phpcs:ignore
$digi_ids['f8'] = (int) $page;
file_put_contents( $digi_dir . '/ids.json', wp_json_encode( $digi_ids ) );
