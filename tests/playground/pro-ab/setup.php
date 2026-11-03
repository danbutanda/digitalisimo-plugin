<?php
/**
 * Segundo paso del blueprint de pro-ab.sh (el constructor ya está cargado). Por cada caso de `cases.json`:
 *
 * - página A `pro-ab-<caso>-a`: un contenedor con el widget de PRO (`widget`) y sus ajustes guardados (`settings`);
 * - página B `pro-ab-<caso>-b`: el mismo árbol convertido con `Converter::tree( $data, $log, true )` (mapas en borrador
 *   incluidos); el `id` del widget es el mismo en A y en B;
 * - las dos con la plantilla «Lienzo» (sin cabecera ni pie del tema: el foco con teclado llega antes al widget) y su CSS
 *   ya generado (`Post::create( $id )->update()`).
 *
 * Deja en `conversion.json` lo que run.cjs necesita: páginas, widget de destino y si está registrado, estado del mapa,
 * sus `drop` y `approx` (los motivos que un caso puede citar con `code`), el registro de la conversión con los códigos
 * del informe y los ajustes de B. Con `map-override.php` (variable MAP del arnés) fija ese mapa con `Registry::set()`
 * antes de convertir: sólo para depurar la mecánica sin el mapa real.
 */
require '/wordpress/wp-load.php';

const DIGI_AB_DIR = '/wordpress/wp-content/probe';

function digi_ab_fail( $message ) {
	file_put_contents( DIGI_AB_DIR . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

/** ID de elemento determinista de 7 caracteres. */
function digi_ab_eid( $seed ) {
	return substr( md5( 'pro-ab-' . $seed ), 0, 7 );
}

/** Ajustes vacíos como objeto (`{}`), como los guarda el editor, a cualquier profundidad del árbol. */
function digi_ab_objects( array $elements ) {
	foreach ( $elements as $i => $element ) {
		$element['settings'] = empty( $element['settings'] ) ? (object) array() : $element['settings'];
		$element['elements'] = digi_ab_objects( isset( $element['elements'] ) && is_array( $element['elements'] ) ? $element['elements'] : array() );
		$elements[ $i ]      = $element;
	}
	return $elements;
}

/** Guarda el árbol de una página (crea la página si no existe) y genera su CSS. Devuelve [ID, estado del CSS]. */
function digi_ab_page( $slug, array $tree ) {
	$existing = get_page_by_path( $slug );
	$id       = $existing ? (int) $existing->ID : (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
	if ( ! $id ) {
		digi_ab_fail( "No se pudo crear la página $slug" );
		return array( 0, '' );
	}
	update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( digi_ab_objects( $tree ) ) ) );
	$css = \Elementor\Core\Files\CSS\Post::create( $id );
	$css->update();
	return array( $id, (string) $css->get_meta( 'status' ) );
}

/** `drop` y `approx` de un mapa (y de sus repetidores, con `repetidor.clave`): motivos que un caso puede citar. */
function digi_ab_reasons( array $map ) {
	$out = array( 'drop' => array(), 'approx' => array() );
	foreach ( array( 'drop', 'approx' ) as $kind ) {
		foreach ( (array) ( $map[ $kind ] ?? array() ) as $key => $reason ) {
			$out[ $kind ][ (string) $key ] = (string) $reason;
		}
		foreach ( (array) ( $map['repeaters'] ?? array() ) as $repeater => $spec ) {
			foreach ( (array) ( $spec[ $kind ] ?? array() ) as $key => $reason ) {
				$out[ $kind ][ $repeater . '.' . $key ] = (string) $reason;
			}
		}
	}
	return $out;
}

if ( ! class_exists( '\\Elementor\\Plugin' ) || ! class_exists( '\\Digitalisimo\\Elements\\Convert\\Converter' ) ) {
	digi_ab_fail( 'El constructor o el módulo de conversión (Digitalisimo\\Elements\\Convert) no están cargados' );
	return;
}
$cases = json_decode( (string) file_get_contents( DIGI_AB_DIR . '/cases.json' ), true );
if ( ! is_array( $cases ) || empty( $cases['cases'] ) ) {
	digi_ab_fail( 'cases.json no es válido o no tiene casos' );
	return;
}
$only = array_filter( array_map( 'trim', explode( ',', (string) @file_get_contents( DIGI_AB_DIR . '/only.txt' ) ) ) );

$override = '';
if ( is_file( DIGI_AB_DIR . '/map-override.php' ) ) {
	$map = require DIGI_AB_DIR . '/map-override.php';
	if ( ! is_array( $map ) || empty( $map['source'] ) || empty( $map['target'] ) ) {
		digi_ab_fail( 'MAP no devuelve un mapa con source y target' );
		return;
	}
	\Digitalisimo\Elements\Convert\Registry::set( (string) $map['source'], $map );
	$override = (string) $map['source'];
}

$widgets = \Elementor\Plugin::$instance->widgets_manager;
$out     = array( 'elementor' => ELEMENTOR_VERSION, 'override' => $override, 'cases' => array() );
foreach ( $cases['cases'] as $slug => $case ) {
	if ( $only ? ! in_array( $slug, $only, true ) : ! empty( $case['pending'] ) ) {
		continue;
	}
	if ( ! preg_match( '/^[a-z0-9-]+$/', (string) $slug ) ) {
		digi_ab_fail( "Nombre de caso no válido: $slug" );
		continue;
	}
	$source = (string) ( $case['widget'] ?? '' );
	$id     = digi_ab_eid( $slug );
	$tree   = array(
		array(
			'id'       => digi_ab_eid( $slug . '-c' ),
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => (array) ( $cases['container'] ?? array() ),
			'elements' => array(
				array(
					'id'         => $id,
					'elType'     => 'widget',
					'widgetType' => $source,
					'settings'   => (array) ( $case['settings'] ?? array() ),
					'elements'   => array(),
				),
			),
		),
	);
	$map    = \Digitalisimo\Elements\Convert\Registry::map( $source, true );
	$target = null !== $map ? (string) $map['target'] : '';
	$log    = array();
	$b_tree = \Digitalisimo\Elements\Convert\Converter::tree( $tree, $log, true );
	$entry  = array(
		'widget'     => $source,
		'element'    => $id,
		'pro'        => \Digitalisimo\Elements\Convert\Catalog::is_pro( $source ),
		'map'        => null !== $map,
		'status'     => null !== $map ? (string) ( $map['status'] ?? 'draft' ) : '',
		'target'     => $target,
		'registered' => array(
			'a' => null !== $widgets->get_widget_types( $source ),
			'b' => '' !== $target && null !== $widgets->get_widget_types( $target ),
		),
		'reasons'    => null !== $map ? digi_ab_reasons( $map ) : array( 'drop' => array(), 'approx' => array() ),
		'log'        => $log,
		'settings_b' => $b_tree[0]['elements'][0]['settings'] ?? array(),
		'error'      => '',
	);
	if ( null === $map ) {
		$entry['error'] = "No hay mapa (ni en borrador) para «{$source}»: B sería igual que A";
	} elseif ( ! $entry['registered']['b'] ) {
		$entry['error'] = "El widget de destino «{$target}» no está registrado: B no pintaría nada";
	} elseif ( count( $log ) !== 1 ) {
		$entry['error'] = 'La conversión no dejó un registro por el widget del caso (' . count( $log ) . ')';
	}
	list( $entry['a'], $entry['css_a'] ) = digi_ab_page( "pro-ab-$slug-a", $tree );
	list( $entry['b'], $entry['css_b'] ) = digi_ab_page( "pro-ab-$slug-b", $b_tree );
	$out['cases'][ $slug ] = $entry;
}
if ( ! $out['cases'] ) {
	digi_ab_fail( 'Ningún caso que medir (CASES no coincide con cases.json)' );
}
// Página que el arnés consulta para saber que el sitio está listo.
if ( ! get_page_by_path( 'pro-ab-ready' ) ) {
	wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'pro-ab-ready', 'post_name' => 'pro-ab-ready', 'post_content' => 'listo' ) );
}
flush_rewrite_rules( false );
delete_transient( 'doing_cron' );
file_put_contents( DIGI_AB_DIR . '/conversion.json', wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
file_put_contents( DIGI_AB_DIR . '/ready.json', wp_json_encode( array( 'cases' => count( $out['cases'] ) ) ) );
