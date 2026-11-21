<?php
/**
 * Extracto automático de IA Tools (auditoría del escritorio, segunda vuelta, 2026-11-20), sin WordPress: el primer
 * guardado REST de una entrada sin extracto ya no hace un wp_update_post() anidado desde save_post (repetía todos los
 * save_post y creaba otra revisión); el extracto se calcula en wp_insert_post_data, con el mismo texto, sólo en la REST,
 * sólo en entradas y sin pisar un extracto escrito.
 */
define( 'ABSPATH', __DIR__ );
define( 'REST_REQUEST', true );
function fail( $m ) { $GLOBALS['failures'][] = $m; fwrite( STDERR, "FALLA: $m\n" ); }
$GLOBALS['hooks'] = array(); $GLOBALS['updates'] = 0;
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][] = array( $h, $cb[1] ); }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][] = array( $h, $cb[1] ); }
function remove_action() {}
function wp_update_post() { $GLOBALS['updates']++; return 1; }
function wp_is_post_revision() { return false; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( $s ) ); }
function strip_shortcodes( $s ) { return preg_replace( '/\[[^\]]*\]/', '', $s ); }
function wp_unslash( $v ) { return stripslashes( $v ); }
function wp_slash( $v ) { return addslashes( $v ); }
// Como kses sin unfiltered_html (un autor, el administrador de un sitio de una red): wp_filter_post_kses() en
// excerpt_save_pre recibe datos escapados y devuelve addslashes( wp_kses( stripslashes( $data ) ) ).
$GLOBALS['kses'] = false;
function sanitize_post_field( $field, $value ) { return $GLOBALS['kses'] ? addslashes( stripslashes( $value ) ) : $value; }
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-content-automation.php';
$C = 'Digitalisimo_Content_Automation';
$C::init();
if ( in_array( array( 'save_post', 'set_excerpt' ), $GLOBALS['hooks'], true ) ) fail( 'El extracto sigue en save_post con un wp_update_post() anidado.' );
if ( ! in_array( array( 'wp_insert_post_data', 'excerpt_data' ), $GLOBALS['hooks'], true ) || ! method_exists( $C, 'excerpt_data' ) ) fail( 'El extracto debe calcularse en wp_insert_post_data.' );
else {
	$content = addslashes( '<p>Texto con "comillas" y ' . str_repeat( 'palabra ', 40 ) . '</p>[galeria]' );
	$out = $C::excerpt_data( array( 'post_type' => 'post', 'post_excerpt' => '', 'post_content' => $content ), array( 'ID' => 7 ) );
	$ex = stripslashes( $out['post_excerpt'] );
	if ( 0 !== strpos( $ex, 'Texto con "comillas" y palabra' ) || mb_strlen( $ex ) > 161 || '…' !== mb_substr( $ex, -1 ) ) fail( 'Extracto inesperado: ' . $ex );
	$same = $C::excerpt_data( array( 'post_type' => 'post', 'post_excerpt' => 'Mío', 'post_content' => $content ) );
	if ( 'Mío' !== $same['post_excerpt'] ) fail( 'Un extracto escrito no se pisa.' );
	$page = $C::excerpt_data( array( 'post_type' => 'page', 'post_excerpt' => '', 'post_content' => $content ) );
	if ( '' !== $page['post_excerpt'] ) fail( 'Sólo las entradas reciben extracto automático.' );
	if ( $GLOBALS['updates'] ) fail( 'No debe haber wp_update_post().' );
	// Comillas y apóstrofos con y sin unfiltered_html: lo guardado (wp_insert_post quita un nivel con wp_unslash) es el texto.
	foreach ( array( false, true ) as $kses ) {
		$GLOBALS['kses'] = $kses;
		$out = $C::excerpt_data( array( 'post_type' => 'post', 'post_excerpt' => '', 'post_content' => addslashes( "<p>Dijo \"hola\" y l'eau</p>" ) ), array( 'ID' => 8 ) );
		$saved = wp_unslash( $out['post_excerpt'] );
		if ( "Dijo \"hola\" y l'eau" !== $saved ) fail( 'Extracto guardado ' . ( $kses ? 'por un autor (kses)' : 'con unfiltered_html' ) . ': ' . $saved );
	}
}
if ( ! empty( $GLOBALS['failures'] ) ) { fwrite( STDERR, count( $GLOBALS['failures'] ) . " fallas\n" ); exit( 1 ); }
echo "OK: el extracto se calcula al guardar, sin un segundo guardado\n";
