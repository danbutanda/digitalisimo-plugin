<?php
/**
 * DIGITALÍSIMO Tools · SVG minimizados: lo que no se pinta se quita, el dibujo no cambia, y lo que no se puede
 * minimizar con seguridad se deja igual. Con dobles de WordPress para la subida y el lote.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	$GLOBALS['filters']  = array();
	$GLOBALS['attached'] = array();
	$GLOBALS['meta']     = array();
	$GLOBALS['deleted']  = array();
	function add_filter( $tag, $fn, $priority = 10 ) { $GLOBALS['filters'][ $tag ] = $priority; }
	function apply_filters( $tag, $value ) { return array_key_exists( $tag, $GLOBALS['overrides'] ?? array() ) ? $GLOBALS['overrides'][ $tag ] : $value; }
	function wp_basename( $path ) { return basename( $path ); }
	function wp_unique_filename( $dir, $name ) { $base = pathinfo( $name, PATHINFO_FILENAME ); $ext = pathinfo( $name, PATHINFO_EXTENSION ); $out = $name; for ( $i = 1; file_exists( $dir . '/' . $out ); $i++ ) $out = $base . '-' . $i . '.' . $ext; return $out; }
	function wp_get_attachment_metadata( $id ) { return $GLOBALS['meta'][ $id ] ?? array(); }
	function wp_update_attachment_metadata( $id, $meta ) { $GLOBALS['meta'][ $id ] = $meta; return true; }
	function update_attached_file( $id, $file ) { if ( ! empty( $GLOBALS['refuse_attach'] ) ) return false; $GLOBALS['attached'][ $id ] = $file; return true; }
	function get_attached_file( $id ) { return $GLOBALS['attached'][ $id ] ?? ''; }
	function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
	function _wp_relative_upload_path( $path ) { return 'svg/' . basename( $path ); }
	function size_format( $bytes ) { return $bytes . ' B'; }
	function wp_delete_file( $file ) { $GLOBALS['deleted'][] = $file; if ( is_file( $file ) ) unlink( $file ); }
	function check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FALLO: $message\n" ); exit( 1 ); } }

	require __DIR__ . '/../digitalisimo-tools/modules/class-media-svg.php';
	use Digitalisimo\Tools\Media_SVG;

	$dir = sys_get_temp_dir() . '/digi-svg-' . getmypid();
	mkdir( $dir );
	$inkscape = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n" . '<!-- Created with Inkscape -->' . "\n"
		. '<svg width="100" height="100" viewBox="0 0 26.4 26.4" version="1.1" id="svg5" inkscape:version="1.2.2" sodipodi:docname="logo.svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n"
		. '  <title id="title1">Logotipo</title>' . "\n  <desc>Marca</desc>\n"
		. '  <sodipodi:namedview id="namedview7" pagecolor="#ffffff" inkscape:zoom="2.8" inkscape:current-layer="layer1" />' . "\n"
		. '  <defs id="defs2"><linearGradient id="g1"><stop offset="0" stop-color="#245eff"/><stop offset="1" stop-color="#7b2cff"/></linearGradient></defs>' . "\n"
		. '  <metadata id="metadata5"><rdf:RDF><cc:Work rdf:about=""><dc:format>image/svg+xml</dc:format></cc:Work></rdf:RDF></metadata>' . "\n"
		. '  <g inkscape:label="Capa 1" inkscape:groupmode="layer" id="layer1" class="capa" aria-label="Capa">' . "\n"
		. '    <circle style="fill:url(#g1);stroke:none" id="path1" cx="13.2" cy="13.2" r="12" inkscape:connector-curvature="0" sodipodi:type="arc" />' . "\n"
		. '    <use xlink:href="#path1" x="1"/>' . "\n"
		. '    <text xml:space="preserve" style="font-size:4px" x="5" y="15" id="text1"><tspan id="tspan1">Hola  mundo</tspan> y <tspan>más</tspan></text>' . "\n"
		. "  </g>\n</svg>\n";

	// 1. Minimizar: más ligero, mismo dibujo, lo útil se conserva y lo del editor desaparece.
	$min = Media_SVG::minify( $inkscape );
	check( is_string( $min ) && strlen( $min ) < strlen( $inkscape ) / 2, 'Un SVG de Inkscape pesa menos de la mitad al minimizarlo.' );
	foreach ( array( '<title id="title1">Logotipo</title>', '<desc>Marca</desc>', 'id="g1"', 'stop-color="#7b2cff"', 'class="capa"', 'aria-label="Capa"', 'fill:url(#g1)', 'xlink:href="#path1"', 'xmlns:xlink="http://www.w3.org/1999/xlink"', '>Hola  mundo</tspan> y <tspan>', 'xml:space="preserve"', 'viewBox="0 0 26.4 26.4"' ) as $kept ) {
		check( false !== strpos( $min, $kept ), 'Se conserva ' . $kept );
	}
	foreach ( array( 'inkscape', 'sodipodi', '<metadata', 'rdf:', 'dc:', '<!--', '<?xml', 'xmlns:svg', "\n" ) as $gone ) {
		check( false === strpos( $min, $gone ), 'Desaparece ' . json_encode( $gone ) );
	}
	$doc = new DOMDocument();
	check( $doc->loadXML( $min ) && 'svg' === $doc->documentElement->localName && 1 === $doc->getElementsByTagName( 'circle' )->length && 1 === $doc->getElementsByTagName( 'use' )->length && 2 === $doc->getElementsByTagName( 'tspan' )->length, 'El resultado es un SVG válido con los mismos elementos.' );
	check( $min === Media_SVG::minify( $min ) || null === Media_SVG::minify( $min ), 'Minimizar dos veces no cambia nada.' );

	// 2. Lo que se deja igual: ya mínimo, DOCTYPE con entidades, otra codificación, no es SVG, demasiado grande.
	check( null === Media_SVG::minify( '<svg xmlns="http://www.w3.org/2000/svg"><circle r="1"/></svg>' ), 'Un SVG ya mínimo no se toca.' );
	check( null === Media_SVG::minify( '<!DOCTYPE svg [ <!ENTITY ns_svg "http://www.w3.org/2000/svg"> ]><svg xmlns="&ns_svg;"><!-- c --><circle r="1"/></svg>' ), 'Una DOCTYPE con entidades (Illustrator) no se toca.' );
	check( null === Media_SVG::minify( '<?xml version="1.0" encoding="ISO-8859-1"?><svg xmlns="http://www.w3.org/2000/svg"><!-- c --><circle r="1"/></svg>' ), 'Otra codificación declarada no se toca.' );
	check( null === Media_SVG::minify( '<p>no</p>' ) && null === Media_SVG::minify( '' ) && null === Media_SVG::minify( '<svg' ), 'Lo que no es un SVG se deja.' );
	check( null === Media_SVG::minify( '<svg xmlns="http://www.w3.org/2000/svg"><!-- c -->' . str_repeat( '<circle r="1"/>', 200000 ) . '</svg>' ), 'Un archivo mayor que el tope se deja.' );
	// Un espacio significativo dentro de un texto sin xml:space tampoco se toca.
	$spaced = '<svg xmlns="http://www.w3.org/2000/svg"><!-- c --><text><tspan>a</tspan> <tspan>b</tspan></text></svg>';
	check( '<svg xmlns="http://www.w3.org/2000/svg"><text><tspan>a</tspan> <tspan>b</tspan></text></svg>' === Media_SVG::minify( $spaced ), 'El espacio entre dos tspan se conserva.' );
	// Sin red: un DOCTYPE externo no se descarga (y como no tiene subconjunto interno, se quita).
	$external = '<?xml version="1.0"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg xmlns="http://www.w3.org/2000/svg"><!-- c --><circle r="1"/></svg>';
	check( '<svg xmlns="http://www.w3.org/2000/svg"><circle r="1"/></svg>' === Media_SVG::minify( $external ), 'La DOCTYPE externa se quita sin descargar nada.' );

	// 3. Subida: sólo SVG sin error, con el tamaño actualizado; el filtro va después del saneado del constructor.
	Media_SVG::init();
	check( 20 === ( $GLOBALS['filters']['wp_handle_upload_prefilter'] ?? 0 ) && 20 === ( $GLOBALS['filters']['wp_handle_sideload_prefilter'] ?? 0 ), 'Los filtros de subida van con prioridad 20, tras el saneado del constructor.' );
	file_put_contents( $dir . '/up.svg', $inkscape );
	$file = Media_SVG::upload( array( 'name' => 'logo.svg', 'tmp_name' => $dir . '/up.svg', 'size' => strlen( $inkscape ), 'error' => 0 ) );
	check( $file['size'] === strlen( $min ) && file_get_contents( $dir . '/up.svg' ) === $min, 'Al subir, el temporal queda minimizado y el tamaño se actualiza.' );
	file_put_contents( $dir . '/up.svg', $inkscape );
	$file = Media_SVG::upload( array( 'name' => 'logo.svg', 'tmp_name' => $dir . '/up.svg', 'size' => strlen( $inkscape ), 'error' => 'Rechazado' ) );
	check( file_get_contents( $dir . '/up.svg' ) === $inkscape, 'Una subida rechazada no se toca.' );
	file_put_contents( $dir . '/up.png', $inkscape );
	Media_SVG::upload( array( 'name' => 'logo.png', 'tmp_name' => $dir . '/up.png', 'size' => 1, 'error' => 0 ) );
	check( file_get_contents( $dir . '/up.png' ) === $inkscape, 'Otro tipo de archivo no se toca.' );
	$GLOBALS['overrides'] = array( 'digitalisimo_tools_svg_minify' => false );
	file_put_contents( $dir . '/up.svg', $inkscape );
	Media_SVG::upload( array( 'name' => 'logo.svg', 'tmp_name' => $dir . '/up.svg', 'size' => 1, 'error' => 0 ) );
	check( file_get_contents( $dir . '/up.svg' ) === $inkscape, 'El filtro digitalisimo_tools_svg_minify apaga la minimización al subir.' );
	$GLOBALS['overrides'] = array();

	// 4. Lote: el original se conserva, el adjunto apunta al minimizado y los metadatos llevan el archivo nuevo.
	file_put_contents( $dir . '/logo.svg', $inkscape );
	$GLOBALS['attached'][7] = $dir . '/logo.svg';
	$GLOBALS['meta'][7]     = array( 'width' => 100, 'height' => 100, 'file' => 'svg/logo.svg' );
	$result = Media_SVG::optimize_attachment( 7, $dir . '/logo.svg' );
	check( 'done' === $result['status'] && $dir . '/logo.min.svg' === $result['path'] && file_get_contents( $dir . '/logo.min.svg' ) === $min, 'El lote escribe logo.min.svg junto al original.' );
	check( file_get_contents( $dir . '/logo.svg' ) === $inkscape, 'El original se conserva.' );
	check( $GLOBALS['attached'][7] === $dir . '/logo.min.svg' && 'svg/logo.min.svg' === $GLOBALS['meta'][7]['file'] && strlen( $min ) === $GLOBALS['meta'][7]['filesize'] && 100 === $GLOBALS['meta'][7]['width'], 'El adjunto y sus metadatos apuntan al minimizado y conservan las dimensiones.' );
	check( false !== strpos( $result['message'], 'logo.svg → logo.min.svg' ), 'El mensaje dice qué archivo se aplicó: ' . $result['message'] );
	$result = Media_SVG::optimize_attachment( 8, $dir . '/logo.min.svg' );
	check( 'kept' === $result['status'] && ! file_exists( $dir . '/logo.min.min.svg' ), 'Un SVG ya mínimo se conserva sin escribir nada.' );
	check( 'failed' === Media_SVG::optimize_attachment( 9, $dir . '/no-existe.svg' )['status'], 'Sin archivo, falla con aviso.' );
	file_put_contents( $dir . '/otro.svg', $inkscape );
	$GLOBALS['attached'][10] = $dir . '/otro.svg';
	$GLOBALS['refuse_attach'] = true;
	$result = Media_SVG::optimize_attachment( 10, $dir . '/otro.svg' );
	check( 'failed' === $result['status'] && ! file_exists( $dir . '/otro.min.svg' ) && $dir . '/otro.svg' === $GLOBALS['attached'][10], 'Si el adjunto no se puede cambiar, se borra el minimizado y el adjunto sigue en el original.' );

	foreach ( glob( $dir . '/*' ) as $f ) unlink( $f );
	rmdir( $dir );
	echo "DIGITALÍSIMO Tools: SVG minimizados al subir y por lotes, con el mismo dibujo y sin tocar lo que no se puede minimizar con seguridad.\n";
}
