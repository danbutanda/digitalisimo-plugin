<?php
/** RM15: las abilities de escritura son opt-in, con permisos, saneado del editor e historial para deshacer. */
define( 'ABSPATH', __DIR__ );
function add_action() {} function add_filter() {}
function sanitize_text_field( $v ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $v ) ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function esc_url_raw( $v, $p = null ) { return preg_match( '#^https?://#i', (string) $v ) ? (string) $v : ''; }
require __DIR__ . '/../digitalisimo-seo/includes/class-settings.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-abilities-write.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$w    = Digitalisimo_Integrations_SEO_Abilities_Write::class;
$defs = $w::definitions();
check( array( 'update-post-seo', 'revert-post-seo', 'create-redirection' ) === array_keys( $defs ), 'Tres abilities de escritura.' );
foreach ( $defs as $name => $d ) {
	check( false === $d[5]['readonly'] && false === $d[5]['destructive'], "Anotadas como escritura no destructiva: $name" );
	check( false === $d[2]['additionalProperties'], "Entrada cerrada: $name" );
	check( in_array( $d[4][1], array( 'can_edit', 'can_manage' ), true ), "Permiso propio (opt-in + capacidad): $name" );
}
check( 'can_manage' === $defs['create-redirection'][4][1], 'Las redirecciones exigen administrar el sitio.' );
check( ! isset( $defs['update-post-seo'][2]['properties']['content'], $defs['update-post-seo'][2]['properties']['status'], $defs['update-post-seo'][2]['properties']['slug'] ), 'Nunca el texto, el estado ni el enlace del contenido.' );
$changes = $w::changes( array( 'title' => 'Viejo', 'noindex' => '' ), array( 'title' => '<b>%title%</b> %sep% %date(Y)%', 'description' => '%description%', 'noindex' => true, 'nofollow' => false, 'keywords' => 'uno, dos,uno, tres', 'canonical' => 'javascript:alert(1)', 'otro' => 'x' ) );
check( array( 'Viejo', '%title% %sep% %date(Y)%' ) === $changes['title'] && array( '', '%description%' ) === $changes['description'], 'Las variables sobreviven al saneado: ' . json_encode( $changes ) );
check( array( '', '1' ) === $changes['noindex'] && ! isset( $changes['nofollow'] ) && ! isset( $changes['canonical'] ) && ! isset( $changes['otro'] ), 'Sólo cambia lo que cambia; una URL peligrosa queda vacía y no cuenta.' );
check( array( '', 'uno,dos,tres' ) === $changes['keywords'], 'Keywords únicas, como mucho cinco.' );
check( array() === $w::changes( array( 'title' => 'Igual' ), array( 'title' => 'Igual' ) ), 'Repetir el mismo valor no registra nada (idempotente).' );
echo "Abilities de escritura: opt-in, permisos, saneado del editor e historial.\n";
