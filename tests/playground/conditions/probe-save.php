<?php
/**
 * Segunda petición de la prueba de condiciones: un administrador guarda desde el editor con la caché de elementos
 * activa y después entra un visitante. En WordPress el guardado es una petición AJAX aparte; aquí también.
 */
define( 'WP_USE_THEMES', true );
require '/wordpress/wp-load.php';
$result = json_decode( (string) file_get_contents( '/wordpress/wp-content/probe/result.json' ), true );
$result = is_array( $result ) ? $result : array();
$cases  = $result['_cases'] ?? array();
$render = static function ( $id ) {
	$GLOBALS['wp_query']     = new WP_Query( array( 'page_id' => $id ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['post']         = get_post( $id );
	setup_postdata( $GLOBALS['post'] );
	\Elementor\Plugin::$instance->documents->get( $id, false );
	$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
	wp_reset_postdata();
	return $html;
};
$check = static function ( $html, array $hidden, array $visible ) {
	$problems = array();
	foreach ( $hidden as $text ) {
		if ( false !== strpos( $html, $text ) ) {
			$problems[] = 'se muestra ' . $text;
		}
	}
	foreach ( $visible as $text ) {
		if ( false === strpos( $html, $text ) ) {
			$problems[] = 'falta ' . $text;
		}
	}
	return $problems;
};
// Guardado desde el editor por un administrador con la caché de elementos activa; después, la primera visita es anónima.
update_option( 'elementor_element_cache_ttl', '24' );
$result['tras-guardar'] = array();
foreach ( array( 'nativo-contenedor', 'propio-contenedor', 'heredado-contenedor', 'plantilla-acordeon' ) as $name ) {
	$doc_post = ! empty( $cases[ $name ][2] ) ? get_post( (int) $cases[ $name ][2] ) : null;
	if ( ! $doc_post ) {
		$result['tras-guardar'][ $name ] = array( 'no existe la página digi-cond-' . $name . '-24' );
		continue;
	}
	list( $anon_hidden, $anon_visible ) = array( $cases[ $name ][0], $cases[ $name ][1] );
	delete_post_meta( $doc_post->ID, '_elementor_element_cache' );
	wp_set_current_user( 1 );
	\Elementor\Plugin::$instance->editor->set_edit_mode( true );
	$document = \Elementor\Plugin::$instance->documents->get( $doc_post->ID, false );
	\Elementor\Plugin::$instance->documents->ajax_save( array( 'editor_post_id' => $doc_post->ID, 'status' => 'publish', 'elements' => $document->get_elements_raw_data(), 'settings' => array() ) );
	\Elementor\Plugin::$instance->documents->restore_document();
	\Elementor\Plugin::$instance->editor->set_edit_mode( false );
	$saved_id = (int) $doc_post->ID;
	wp_set_current_user( 0 );
	$html = $render( $saved_id );
	$result['tras-guardar'][ $name ] = $check( $html, $anon_hidden, $anon_visible );
	wp_set_current_user( 0 );
	$result['tras-guardar'][ $name . '-2' ] = $check( $render( $saved_id ), $anon_hidden, $anon_visible );
}
unset( $result['_cases'] );
file_put_contents( '/wordpress/wp-content/probe/result.json', wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
