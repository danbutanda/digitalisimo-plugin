<?php
/** RM15: las abilities de SEO son de sólo lectura, con permisos y esquemas válidos. */
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-abilities.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$defs = Digitalisimo_Integrations_SEO_Abilities::definitions();
check( 8 === count( $defs ), 'Ocho abilities.' );
foreach ( $defs as $name => $d ) {
	check( preg_match( '/^[a-z0-9-]+$/', $name ), "Nombre válido para la Abilities API: $name" );
	check( is_string( $d[0] ) && '' !== $d[0] && is_string( $d[1] ) && strlen( $d[1] ) > 20, "Etiqueta y descripción: $name" );
	check( 'object' === $d[2]['type'] && false === $d[2]['additionalProperties'], "Entrada cerrada: $name" );
	check( is_callable( $d[3] ) && is_callable( $d[4] ), "Ejecutor y permiso: $name" );
	check( in_array( $d[4][1], array( 'can_edit', 'can_manage' ), true ), "Permiso por objeto o de administración: $name" );
	if ( in_array( 'post_id', $d[2]['required'] ?? array(), true ) ) check( 'can_edit' === $d[4][1], "Lo de un contenido exige edit_post: $name" );
}
// Sólo lectura por construcción: el archivo no llama a nada que escriba.
$tokens = token_get_all( file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-seo-abilities.php' ) );
$writes = array( 'update_post_meta', 'delete_post_meta', 'add_post_meta', 'update_option', 'delete_option', 'add_option', 'update_site_option', 'wp_update_post', 'wp_insert_post', 'wp_delete_post', 'update_term_meta', 'insert', 'update', 'delete', 'query', 'save', 'set_status', 'start', 'run_batch' );
foreach ( $tokens as $i => $token ) {
	if ( ! is_array( $token ) || T_STRING !== $token[0] || ! in_array( $token[1], $writes, true ) ) continue;
	$next = $tokens[ $i + 1 ] ?? '';
	check( '(' !== ( is_array( $next ) ? $next[1] : $next ), 'Una ability llama a una función que escribe: ' . $token[1] . ' (línea ' . $token[2] . ')' );
}
check( false !== strpos( file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-seo-abilities.php' ), "'readonly' => true, 'destructive' => false" ), 'Anotadas como de sólo lectura y no destructivas.' );
echo "Abilities de SEO: ocho de sólo lectura, con permisos y entradas cerradas.\n";
