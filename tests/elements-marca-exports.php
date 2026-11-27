<?php
/**
 * Marca propia (docs/MARCA_PROPIA.md): las exportaciones que el cliente descarga salen con los nombres propios y al
 * entrar se aceptan los dos (DECISIONES). Cubre Marca\Exports sin WordPress:
 * - X1 la plantilla suelta: nombre del archivo y plantilla de página propios; el contenido del cliente igual;
 * - X2 el WXR: tipo, taxonomías, metas y plantilla de página propios; el contenido igual;
 * - X3 el importador de WordPress acepta los dos nombres;
 * - X4 escribir `digitalisimo_canvas` en `_wp_page_template` guarda `elementor_canvas`.
 * Falla con el código anterior (sin class-exports.php).
 */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['fails']  = 0;
$GLOBALS['checks'] = 0;
function check( $ok, $what, $detail = '' ) {
	$GLOBALS['checks']++;
	if ( ! $ok ) {
		$GLOBALS['fails']++;
		echo 'FALLA ' . $what . ( '' !== $detail ? ' · ' . ( is_string( $detail ) ? $detail : json_encode( $detail ) ) : '' ) . "\n";
	}
}
$el = dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-marca';
if ( ! is_file( $el . '/class-exports.php' ) ) {
	echo "FALLA no existe class-exports.php (código anterior)\n";
	exit( 1 );
}
function add_filter() {}
function add_action() {}
function apply_filters( $h, $v ) {
	return $v;
}
$GLOBALS['meta'] = array();
$GLOBALS['filter'] = '';
function current_filter() {
	return $GLOBALS['filter'];
}
function update_post_meta( $id, $k, $v ) {
	$GLOBALS['meta'][ $id ][ $k ] = $v;
	return true;
}
function add_post_meta( $id, $k, $v ) {
	return update_post_meta( $id, $k, $v );
}
foreach ( array( 'class-map.php', 'class-addons.php', 'class-toggle.php', 'class-transform.php', 'class-exports.php' ) as $f ) {
	require $el . '/' . $f;
}
use Digitalisimo\Elements\Marca\Exports;
use Digitalisimo\Elements\Marca\Map;

Map::set_types( array( 'elementor_library' => 'digitalisimo_library', 'elementor_library_type' => 'digitalisimo_library_type' ) );

// X1 --------------------------------------------------------------------------------------------------------------------
check( 'dg-12-2026-11-27.json' === Exports::file_name( 'elementor-12-2026-11-27.json' ), 'X1 el archivo sale como dg-…json' );
$json = json_encode( array( 'content' => array( array( 'settings' => array( 'title' => 'Guía de Elementor', 'css' => '.elementor-x{}' ) ) ), 'page_settings' => array( 'template' => 'elementor_canvas', 'custom' => 'elementor_otro' ), 'version' => '0.4', 'title' => 'Mi Elementor', 'type' => 'page' ) );
$out  = json_decode( Exports::template_json( $json ), true );
check( 'digitalisimo_canvas' === $out['page_settings']['template'] && 'elementor_otro' === $out['page_settings']['custom'], 'X1 la plantilla de página con nombre propio; otros valores igual', $out );
check( json_decode( $json, true )['content'] === $out['content'] && 'Mi Elementor' === $out['title'], 'X1 el contenido y el título del cliente no cambian', $out );

// X2 --------------------------------------------------------------------------------------------------------------------
$xml = '<item><title>Mi Elementor</title><content:encoded><![CDATA[<div class="elementor-x">Elementor</div>]]></content:encoded>'
	. '<wp:post_type><![CDATA[elementor_library]]></wp:post_type>'
	. '<category domain="elementor_library_type" nicename="page"><![CDATA[page]]></category>'
	. '<wp:postmeta><wp:meta_key><![CDATA[_elementor_data]]></wp:meta_key><wp:meta_value><![CDATA[[{"elType":"container"}]]]></wp:meta_value></wp:postmeta>'
	. '<wp:postmeta><wp:meta_key><![CDATA[_wp_page_template]]></wp:meta_key>' . "\n\t\t" . '<wp:meta_value><![CDATA[elementor_canvas]]></wp:meta_value></wp:postmeta></item>';
$wxr = Exports::wxr( $xml );
foreach ( array( '<wp:post_type><![CDATA[digitalisimo_library]]>', 'domain="digitalisimo_library_type"', '<wp:meta_key><![CDATA[_dg_data]]>', '<wp:meta_value><![CDATA[digitalisimo_canvas]]>', '<title>Mi Elementor</title>', '<div class="elementor-x">Elementor</div>' ) as $want ) {
	check( false !== strpos( $wxr, $want ), "X2 el WXR lleva «{$want}»", $wxr );
}

// X3 --------------------------------------------------------------------------------------------------------------------
$post = Exports::wxr_import(
	array(
		'post_type' => 'digitalisimo_library',
		'terms'     => array( array( 'domain' => 'digitalisimo_library_type', 'slug' => 'page' ) ),
		'postmeta'  => array( array( 'key' => '_dg_data', 'value' => '[]' ), array( 'key' => '_wp_page_template', 'value' => 'digitalisimo_canvas' ), array( 'key' => '_elementor_version', 'value' => '4.3.5' ) ),
	)
);
check( 'elementor_library' === $post['post_type'] && 'elementor_library_type' === $post['terms'][0]['domain'], 'X3 tipo y taxonomía con el nombre del motor', $post );
check( '_elementor_data' === $post['postmeta'][0]['key'] && 'elementor_canvas' === $post['postmeta'][1]['value'] && '_elementor_version' === $post['postmeta'][2]['key'], 'X3 metas y plantilla de página; el nombre viejo entra igual', $post );

// X4 --------------------------------------------------------------------------------------------------------------------
$GLOBALS['filter'] = 'update_post_metadata';
check( true === Exports::page_template( null, 7, '_wp_page_template', 'digitalisimo_canvas' ) && 'elementor_canvas' === $GLOBALS['meta'][7]['_wp_page_template'], 'X4 digitalisimo_canvas se guarda como elementor_canvas' );
check( null === Exports::page_template( null, 7, '_wp_page_template', 'default' ) && null === Exports::page_template( null, 7, 'otra', 'digitalisimo_canvas' ), 'X4 otros valores y otras metas pasan igual' );

echo "\n$GLOBALS[fails] fallos de $GLOBALS[checks] comprobaciones\n";
exit( $GLOBALS['fails'] ? 1 : 0 );
