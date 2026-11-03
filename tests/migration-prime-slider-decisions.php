<?php
/**
 * F8 · decisiones de Prime Slider (V.10.7, §3.E.1): los 57 mapas de `prime-slider/maps/` (38 del frente E y 19 del F)
 * contra el inventario de B (`docs/prime-slider-inventory.json`) y la tabla de familias de la especificación.
 *
 * Por cada ID: un mapa válido (familia, decisión, destino, respaldo y motivo), la familia y el dueño de la tabla, un
 * destino y un respaldo de los admitidos para su familia, descartes sólo en la familia M con su motivo, el plugin del
 * inventario y, con los valores estáticos de B, el repetidor que leen los constructores. Ningún mapa sin inventario ni
 * inventario sin mapa. Sustituye a la `.mjs` de la v1: lee los mapas, no un JSON compartido.
 *
 * php-wasm sale con 0 aunque haya un error fatal: se juzga por la línea final.
 */

require __DIR__ . '/fixtures/f8-wp.php';
f8_reset();
f8_load_migration( array( 'translator', 'context', 'report' ) );
require_once dirname( __DIR__ ) . '/digitalisimo-migration/legacy/prime-slider/class-maps.php';

use Digitalisimo\Elements\Legacy\Prime_Slider\Maps;

$check = 'f8_check';
$root  = dirname( __DIR__ );

/** Tabla de familias de §3.E.1 (`research-ps.md` §2.4) y su frente. */
$families = array(
	'A' => array( 'E', array( 'general', 'isolate', 'dragon', 'sequester', 'mount' ) ),
	'B' => array( 'E', array( 'omatic', 'sniper', 'coddle', 'escape', 'fortune', 'titanic', 'avatar' ) ),
	'C' => array( 'E', array( 'elysium', 'tango', 'monster' ) ),
	'D' => array( 'E', array( 'custom' ) ),
	'E' => array( 'F', array( 'blog', 'flogia', 'fluent' ) ),
	'F' => array( 'F', array( 'mercury', 'storker', 'knily', 'pacific', 'rubix', 'vertex', 'fiestar', 'marble', 'material' ) ),
	'G' => array( 'F', array( 'woocommerce', 'woolamp', 'woostand', 'woocircle', 'wooexpand', 'woohotspot' ) ),
	'H' => array( 'E', array( 'cloud', 'panorama', 'expo-slider', 'cards-stack', 'super-flow' ) ),
	'I' => array( 'E', array( 'astoria', 'paranoia', 'reveal', 'prism', 'pieces', 'crossroad', 'motion', 'turnar', 'ring' ) ),
	'J' => array( 'E', array( 'pandora', 'flexure' ) ),
	'K' => array( 'E', array( 'multiscroll', 'pagepiling' ) ),
	'L' => array( 'F', array( 'event-calendar' ) ),
	'M' => array( 'E', array( 'remote-arrows', 'remote-fraction', 'remote-pagination', 'remote-thumbs' ) ),
);
/** Destinos y respaldos admitidos por familia (§3.E.1, V.10). */
$targets = array(
	'A' => array( array( 'digitalisimo-nested-slider' ), array( 'slides' ) ),
	'B' => array( array( 'digitalisimo-nested-slider' ), array( 'slides' ) ),
	'C' => array( array( 'digitalisimo-logos' ), array( '' ) ),
	'D' => array( array( 'digitalisimo-nested-slider' ), array( 'slides' ) ),
	'E' => array( array( 'loop-carousel' ), array( 'posts' ) ),
	'F' => array( array( 'loop-carousel' ), array( 'posts' ) ),
	'G' => array( array( 'loop-carousel', 'woocommerce-products', 'digitalisimo-nested-slider' ), array( 'woocommerce-products', 'hotspot', '' ) ),
	'H' => array( array( 'media-carousel' ), array( '' ) ),
	'I' => array( array( 'digitalisimo-nested-slider' ), array( 'slides' ) ),
	'J' => array( array( 'digitalisimo-fancy-tabs' ), array( '' ) ),
	'K' => array( array( 'container' ), array( 'slides' ) ),
	'L' => array( array( 'loop-carousel' ), array( 'posts', '' ) ),
	'M' => array( array( '' ), array( '' ) ),
);
$owner = array();
$table = array();
foreach ( $families as $family => $data ) {
	foreach ( $data[1] as $short ) {
		$table[ 'prime-slider-' . $short ] = $family;
		$owner[ 'prime-slider-' . $short ]  = $data[0];
	}
}
$check( 57 === count( $table ), 'la tabla de familias tiene 57 IDs' );
$check( 38 === count( array_keys( $owner, 'E', true ) ) && 19 === count( array_keys( $owner, 'F', true ) ), '38 IDs del frente E y 19 del F (V.10.7)' );

/* ---------------------------------------------------------------- Inventario de B */

$file = $root . '/docs/prime-slider-inventory.json';
$check( is_file( $file ), 'inventario de B (docs/prime-slider-inventory.json)' );
$inventory = json_decode( (string) file_get_contents( $file ), true );
$widgets   = array();
foreach ( (array) ( $inventory['widgets'] ?? array() ) as $widget ) {
	$id = (string) ( $widget['legacy_widget_id'] ?? ( $widget['id'] ?? '' ) );
	if ( '' !== $id ) {
		$widgets[ $id ] = $widget;
	}
}
$check( 57 === count( $widgets ) && 57 === (int) ( $inventory['widget_count'] ?? 0 ), 'inventario: 57 widgets únicos' );
$check( 22 === (int) ( $inventory['by_plugin']['lite'] ?? 0 ) && 35 === (int) ( $inventory['by_plugin']['pro'] ?? 0 ), 'inventario: 22 de Lite y 35 de Pro' );
$check( array() === array_diff( array_keys( $widgets ), array_keys( $table ) ) && array() === array_diff( array_keys( $table ), array_keys( $widgets ) ), 'inventario = tabla de familias' );
$skins = array( 'prime-slider-general' => array( 'crelly', 'meteor', 'slide' ), 'prime-slider-isolate' => array( 'locate', 'slice' ), 'prime-slider-blog' => array( 'coral', 'folio', 'zinest' ) );
foreach ( $skins as $id => $expected ) {
	$got = (array) ( $widgets[ $id ]['skins'] ?? array() );
	sort( $got );
	$check( $expected === $got, $id . ': skins del inventario' );
}

/* ---------------------------------------------------------------- Mapas */

$files = glob( $root . '/digitalisimo-migration/legacy/prime-slider/maps/*.php' );
$check( 57 === count( $files ), 'hay 57 archivos de mapa (hay ' . count( $files ) . ')' );
$all = Maps::all();
$check( array() === array_diff( array_keys( $all ), array_keys( $widgets ) ), 'ningún mapa sin widget en el inventario' );
$vendors = json_decode( (string) file_get_contents( __DIR__ . '/playground/brand-audit/vendors.json' ), true );
$static  = $root . '/digitalisimo-migration/legacy/prime-slider/defaults/';
foreach ( $table as $id => $family ) {
	$map = Maps::get( $id );
	$check( is_array( $map ), $id . ': mapa válido (family, decision, target, fallback y reason)' );
	$check( is_file( $root . '/digitalisimo-migration/legacy/prime-slider/maps/' . $id . '.php' ), $id . ': el archivo se llama como el widget' );
	$check( $family === $map['family'], $id . ': familia ' . $family . ' (es ' . $map['family'] . ')' );
	$check( Maps::get( substr( $id, strlen( 'prime-slider-' ) ) ) === $map, $id . ': también por el ID corto' );
	$descarte = 'DESCARTAR' === $map['decision'];
	$check( $descarte === ( 'M' === $family ), $id . ': sólo la familia M se descarta' );
	$check( in_array( $map['target'], $targets[ $family ][0], true ), $id . ': destino admitido para la familia ' . $family . ' (' . $map['target'] . ')' );
	$check( in_array( $map['fallback'], $targets[ $family ][1], true ), $id . ': respaldo admitido para la familia ' . $family . ' (' . $map['fallback'] . ')' );
	$reason = trim( (string) $map['reason'] );
	$check( ( function_exists( 'mb_strlen' ) ? mb_strlen( $reason, 'UTF-8' ) : strlen( $reason ) ) >= 20, $id . ': motivo de al menos 20 caracteres' );
	$text = strtolower( $reason . ' ' . json_encode( $map['losses'] ?? array() ) );
	$clean = true;
	foreach ( $vendors['hosts'] as $host ) {
		$clean = $clean && false === strpos( $text, $host );
	}
	$check( $clean, $id . ': textos sin dominios del proveedor' );
	if ( isset( $map['plugin'] ) ) {
		$check( $map['plugin'] === ( $widgets[ $id ]['plugin'] ?? '' ), $id . ': plugin del inventario (' . ( $widgets[ $id ]['plugin'] ?? '?' ) . ')' );
	}
	if ( $descarte ) {
		$check( 'ps.dropped.remote' === ( $map['drop_code'] ?? '' ), $id . ': descarte con su código del informe' );
		continue;
	}
	if ( 'E' === $owner[ $id ] ) {
		// Lo que leen los constructores de E: el repetidor existe en los valores por defecto de B (si ya están).
		$check( is_string( $map['rows'] ?? null ) && is_array( $map['fields'] ?? null ) && $map['fields'], $id . ': filas y campos declarados' );
		$defaults = is_file( $static . $id . '.php' ) ? include $static . $id . '.php' : null;
		if ( is_array( $defaults ) ) {
			$check( isset( $defaults['repeaters'][ $map['rows'] ] ), $id . ': el repetidor «' . $map['rows'] . '» está en los valores por defecto de B' );
		}
	}
}

/* ---------------------------------------------------------------- Valores estáticos de B (C9) */

if ( is_dir( $static ) ) {
	$defaults = glob( $static . '*.php' );
	$check( 57 === count( $defaults ), 'B: 57 archivos de valores por defecto' );
	foreach ( $defaults as $file ) {
		$data = include $file;
		$id   = basename( $file, '.php' );
		$check( is_array( $data ) && $id === ( $data['id'] ?? '' ) && isset( $data['controls'], $data['repeaters'] ) && isset( $widgets[ $id ] ), $id . ': valores por defecto con el formato C9' );
	}
}

f8_done( 'migration-prime-slider-decisions.php', 171 );
