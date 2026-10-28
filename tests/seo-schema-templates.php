<?php
/** RM8: plantillas de schema con condiciones, sin WordPress. */
define( 'ABSPATH', __DIR__ );
class Digitalisimo_Integrations_SEO_Suite { const P = 'digitalisimo_seo_'; }
class Digitalisimo_Integrations_SEO_Variables { public static function replace( $text, $ctx ) { return trim( str_replace( array( '%title%', '%customfield(fecha)%' ), array( $ctx['title'], 10 === $ctx['id'] ? '2026-11-05' : '' ), $text ) ); } }
$GLOBALS['schema_settings'] = array( 'seo_schema_unify' => 1 );
class Digitalisimo_Integrations_Settings { public static function get( $key, $default = '' ) { return $GLOBALS['schema_settings'][ $key ] ?? $default; } }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function sanitize_title( $value ) { return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $value ), '-' ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function taxonomy_exists( $taxonomy ) { return 'category' === $taxonomy; }
function get_the_terms( $id, $taxonomy ) { return 10 === $id ? array( (object) array( 'slug' => 'eventos' ) ) : array( (object) array( 'slug' => 'otra' ) ); }
function wp_list_pluck( $list, $field ) { return array_map( function( $o ) use ( $field ) { return $o->$field; }, $list ); }
function get_the_title( $post ) { return 'Concierto ' . $post->ID; }
require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-rules.php';
require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-graph.php';
function check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FALLO: $message\n" ); exit( 1 ); } }
$g = 'Digitalisimo_Integrations_Schema_Graph';

// Saneado: tipo de la lista, propiedades como objeto JSON sin claves reservadas, filas inválidas fuera.
$clean = $g::sanitize_templates( array(
	array( 'name' => '<b>Eventos</b>', 'type' => 'Event', 'post_types' => array( 'post', 'Post!' ), 'taxonomy' => 'category', 'terms' => 'Eventos, , Otros eventos', 'include' => '', 'exclude' => '7, x, 7', 'properties' => '{"@type":"Thing","@id":"x","startDate":"%customfield(fecha)%","location":{"@type":"Place","name":"%title%"}}' ),
	array( 'type' => 'Product', 'post_types' => array( 'post' ) ),
	array( 'type' => 'Recipe', 'post_types' => array( 'post' ), 'remove' => '1' ),
	array( 'type' => 'Course', 'properties' => '[1,2]' ),
	array( 'type' => '', 'name' => 'vacía' ),
) );
check( 2 === count( $clean ), 'Sólo quedan las filas con un tipo válido y sin «Quitar».' );
check( 'Eventos' === $clean[0]['name'] && array( 'post' ) === $clean[0]['post_types'] && array( 'eventos', 'otros-eventos' ) === $clean[0]['terms'] && array( 7 ) === $clean[0]['exclude'], 'Nombre, tipos, términos e IDs se sanean.' );
check( '{"startDate":"%customfield(fecha)%","location":{"@type":"Place","name":"%title%"}}' === $clean[0]['properties'], 'Las propiedades pierden @type, @id, @context y @graph y conservan las variables.' );
check( '' === $clean[1]['properties'] && array() === $clean[1]['post_types'], 'Un JSON que no es objeto se descarta.' );
check( $g::TEMPLATES_MAX === count( $g::sanitize_templates( array_fill( 0, 30, array( 'type' => 'Event', 'post_types' => array( 'post' ) ) ) ) ), 'Como mucho 20 plantillas.' );

// Condiciones: fallan cerradas.
check( ! $g::template_matches( $clean[1], 'post', 1, array() ), 'Sin tipos de contenido, nunca se aplica.' );
check( $g::template_matches( $clean[0], 'post', 10, array( 'eventos' ) ), 'Tipo y término coinciden.' );
check( ! $g::template_matches( $clean[0], 'post', 11, array( 'otra' ) ), 'Sin el término, no se aplica.' );
check( ! $g::template_matches( $clean[0], 'post', 7, array( 'eventos' ) ), 'Un ID excluido no se aplica.' );
check( ! $g::template_matches( $clean[0], 'page', 10, array( 'eventos' ) ), 'Otro tipo de contenido no se aplica.' );
check( ! $g::template_matches( array( 'post_types' => array( 'post' ), 'terms' => array( 'eventos' ), 'taxonomy' => '' ), 'post', 10, array( 'eventos' ) ), 'Términos sin taxonomía no se aplican.' );
check( $g::template_matches( array( 'post_types' => array( 'post' ), 'include' => array( 3 ) ), 'post', 3, array() ) && ! $g::template_matches( array( 'post_types' => array( 'post' ), 'include' => array( 3 ) ), 'post', 4, array() ), 'La lista de IDs incluidos limita.' );

// Aplicación: entidad y nodo, con variables; lo vacío no se publica.
$GLOBALS['schema_settings']['seo_schema_templates'] = $clean;
$post = (object) array( 'ID' => 10, 'post_type' => 'post' );
check( 'Event' === $g::entity_type( $post ), 'La plantilla aporta la entidad principal.' );
$nodes = $g::template_nodes( $post );
check( array( array( '@type' => 'Event', 'startDate' => '2026-11-05', 'location' => array( '@type' => 'Place', 'name' => 'Concierto 10' ) ) ) === $nodes, 'El nodo lleva las propiedades con las variables resueltas.' );
$other = (object) array( 'ID' => 12, 'post_type' => 'post' );
$GLOBALS['schema_settings']['seo_schema_templates'][0]['terms'] = array();
$GLOBALS['schema_settings']['seo_schema_templates'][0]['taxonomy'] = '';
check( 'Event' === $g::entity_type( $other ) && array( array( '@type' => 'Event', 'location' => array( '@type' => 'Place', 'name' => 'Concierto 12' ) ) ) === $g::template_nodes( $other ), 'Una variable vacía quita la propiedad: nunca se inventa.' );
$product = (object) array( 'ID' => 13, 'post_type' => 'product' );
check( null === $g::template_for( $product ), 'Los productos nunca usan plantillas.' );
$GLOBALS['meta'][14]['digitalisimo_seo_schema_type'] = 'Article';
$chosen = (object) array( 'ID' => 14, 'post_type' => 'post' );
check( 'Article' === $g::entity_type( $chosen ) && array() === $g::template_nodes( $chosen ), 'La elección del editor gana: ni entidad ni nodo de la plantilla.' );
echo "Plantillas de schema: saneado, condiciones y nodos correctos.\n";
