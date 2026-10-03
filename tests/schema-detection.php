<?php
/** Selección explícita y detección de tipos de contenido sin cargar WordPress. */
define( 'ABSPATH', __DIR__ );
class Digitalisimo_Integrations_SEO_Suite { const P = 'digitalisimo_seo_'; }
class Digitalisimo_Integrations_Settings { public static function get( $key, $default = '' ) { return 'seo_schema_unify' === $key ? 1 : $default; } }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['schema_meta'][ $id ][ $key ] ?? ''; }
require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-graph.php';
$g = 'Digitalisimo_Integrations_Schema_Graph';
$check = function( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FALLO: $message\n" ); exit( 1 ); } };
$post = (object) array( 'ID' => 9, 'post_type' => 'page' );
$key = Digitalisimo_Integrations_SEO_Suite::P . 'schema_type';
$GLOBALS['schema_meta'][9][$key] = 'service'; // Valor guardado por versiones anteriores.
$check( 'Service' === $g::chosen_type( 9 ) && 'Service' === $g::entity_type( $post ), 'un Service anterior no se pierde' );
$GLOBALS['schema_meta'][9][$key] = 'Article';
$check( 'Article' === $g::entity_type( $post ), 'selección explícita de Article' );
$GLOBALS['schema_meta'][9][$key] = 'Product';
$check( '' === $g::entity_type( $post ), 'una página común no se declara producto sin datos' );
$GLOBALS['schema_meta'][9][$key] = 'Person';
$check( 'Person' === $g::chosen_type( 9 ) && '' === $g::entity_type( $post ), 'Person se maneja como ProfilePage' );
$GLOBALS['schema_meta'][9][$key] = '';
$post->post_type = 'servicios';
$check( 'Service' === $g::entity_type( $post ), 'CPT de servicios explícito' );
$post->post_type = 'post';
$check( 'BlogPosting' === $g::entity_type( $post ), 'entrada publicada como BlogPosting' );
$post->post_type = 'product';
$check( '' === $g::entity_type( $post ), 'CPT product sin WooCommerce no publica datos inventados' );
echo "Detección Schema: OK\n";
