<?php
/** RM10: el saneado de plantillas conserva las variables que sanitize_text_field() tomaría por octetos (%de, %da, %ca). */
define( 'ABSPATH', __DIR__ );
function _sanitize( $str, $keep_newlines ) {
	$filtered = trim( strip_tags( (string) $str ) );
	if ( ! $keep_newlines ) $filtered = preg_replace( '/[\r\n\t ]+/', ' ', $filtered );
	while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) $filtered = str_replace( $match[0], '', $filtered ); // Como _sanitize_text_fields() de WordPress.
	return trim( $filtered );
}
function sanitize_text_field( $s ) { return _sanitize( $s, false ); }
function sanitize_textarea_field( $s ) { return _sanitize( $s, true ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
function add_action() {} function add_filter() {}
require __DIR__ . '/../digitalisimo-seo/includes/class-settings.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$s = Digitalisimo_Integrations_Settings::class;
check( 'scription%' === sanitize_textarea_field( '%description%' ), 'El stub reproduce la pérdida de WordPress.' );
check( '%description% %sep% %date(Y)% · %category%' === $s::sanitize_template( '<b>%description%</b> %sep% %date(Y)% · %category%' ), 'Las variables sobreviven y las etiquetas se quitan.' );
check( "%term%\n%description%" === $s::sanitize_template( "%term%\n%description%", true ) && '%term% %description%' === $s::sanitize_template( "%term%\n%description%" ), 'Saltos de línea sólo en descripciones.' );
check( false === strpos( $s::sanitize_template( '50 %de descuento %title%' ), '%de' ) && '%title%' === substr( $s::sanitize_template( '50 %de descuento %title%' ), -7 ), 'Un octeto suelto sigue quitándose.' );
check( '' === $s::sanitize_template( array( 'x' ) ), 'Un valor que no es texto queda vacío.' );
check( in_array( 'seo_description_taxonomy', $s::template_keys(), true ) && 8 === count( $s::template_keys() ), 'Las ocho plantillas globales.' );
echo "Plantillas: las variables sobreviven al saneado de WordPress.\n";
