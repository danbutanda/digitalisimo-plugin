<?php
/**
 * DIGITALÍSIMO Tools · Optimizar medios: GIF estático a WebP por el editor, GIF animado a WebP animado entero (sin
 * tamaños reducidos) o conservado si el servidor no puede, SVG minimizado, reversión completa si algo falla, y los
 * estados de la Biblioteca. Con dobles de WordPress; los GIF y el WebP animado se arman byte a byte.
 */
namespace {
	$abspath = sys_get_temp_dir() . '/digi-webp-' . getmypid() . '/';
	mkdir( $abspath . 'wp-admin/includes', 0777, true );
	file_put_contents( $abspath . 'wp-admin/includes/image.php', '<?php' );
	define( 'ABSPATH', $abspath );
	$GLOBALS['filters'] = array();
	$GLOBALS['hooks']   = array();
	$GLOBALS['posts']   = array();
	$GLOBALS['meta']    = array();
	$GLOBALS['files']   = array();
	$GLOBALS['amd']     = array();
	$GLOBALS['deleted'] = array();
	$GLOBALS['editor']  = null;
	function add_action( $tag, $fn, $p = 10, $n = 1 ) { $GLOBALS['hooks'][ $tag ] = true; }
	function add_filter( $tag, $fn, $p = 10, $n = 1 ) { $GLOBALS['hooks'][ $tag ] = true; }
	function remove_filter() {}
	function has_filter( $tag ) { return isset( $GLOBALS['filters'][ $tag ] ); }
	function apply_filters( $tag, $value, ...$args ) { return isset( $GLOBALS['filters'][ $tag ] ) ? call_user_func( $GLOBALS['filters'][ $tag ], $value, ...$args ) : $value; }
	function get_attached_file( $id ) { return $GLOBALS['files'][ $id ] ?? ''; }
	function update_attached_file( $id, $file ) { if ( ! empty( $GLOBALS['refuse_attach'] ) ) return false; $GLOBALS['files'][ $id ] = $file; return true; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
	function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
	function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
	function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
	function get_post_mime_type( $id ) { return $GLOBALS['posts'][ $id ]->post_mime_type ?? ''; }
	function wp_update_post( $data, $error = false ) { if ( ! empty( $GLOBALS['refuse_update'] ) ) return 0; foreach ( $data as $k => $v ) if ( 'ID' !== $k ) $GLOBALS['posts'][ $data['ID'] ]->$k = $v; return $data['ID']; }
	function wp_get_attachment_metadata( $id ) { return $GLOBALS['amd'][ $id ] ?? array(); }
	function wp_update_attachment_metadata( $id, $meta ) { $GLOBALS['amd'][ $id ] = $meta; return true; }
	function wp_generate_attachment_metadata( $id, $file ) { $thumb = dirname( $file ) . '/' . pathinfo( $file, PATHINFO_FILENAME ) . '-150x150.webp'; file_put_contents( $thumb, 'x' ); return array( 'file' => 'up/' . basename( $file ), 'width' => 1, 'height' => 1, 'sizes' => array( 'thumbnail' => array( 'file' => basename( $thumb ) ) ) ); }
	function wp_normalize_path( $p ) { return str_replace( '\\', '/', $p ); }
	function wp_basename( $p ) { return basename( $p ); }
	function wp_unique_filename( $dir, $name ) { $base = pathinfo( $name, PATHINFO_FILENAME ); $ext = pathinfo( $name, PATHINFO_EXTENSION ); $out = $name; for ( $i = 1; file_exists( $dir . '/' . $out ); $i++ ) $out = $base . '-' . $i . '.' . $ext; return $out; }
	function wp_delete_file( $f ) { $GLOBALS['deleted'][] = $f; if ( is_file( $f ) ) unlink( $f ); }
	function _wp_relative_upload_path( $p ) { return 'up/' . basename( $p ); }
	function size_format( $b ) { return $b . ' B'; }
	function is_wp_error( $v ) { return $v instanceof WP_Error; }
	function wp_get_image_editor( $file ) { return $GLOBALS['editor'] ?: new WP_Error( 'no', 'Sin editor.' ); }
	class WP_Error { public $m; public function __construct( $c = '', $m = '' ) { $this->m = $m; } public function get_error_message() { return $this->m; } }
	class Fake_Editor {
		public $quality = 0; public $webp = true;
		public function supports_mime_type( $m ) { return $this->webp; }
		public function set_quality( $q ) { $this->quality = $q; }
		public function save( $out, $mime ) { file_put_contents( $out, 'RIFF' . pack( 'V', 20 ) . 'WEBPVP8 ' . str_repeat( "\0", 12 ) ); return array( 'path' => $out, 'mime-type' => $mime ); }
	}
	function check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FALLO: $message\n" ); exit( 1 ); } }

	require __DIR__ . '/../digitalisimo-tools/modules/class-media-svg.php';
	require __DIR__ . '/../digitalisimo-tools/modules/class-media-webp.php';
	use Digitalisimo\Tools\Media_WebP;

	$dir = $abspath . 'up';
	mkdir( $dir );
	// GIF de 1×1 (dos colores) armado a mano: un fotograma, y otro con dos fotogramas y bucle.
	$gif_head  = 'GIF89a' . "\x01\x00\x01\x00\x80\x00\x00" . "\x00\x00\x00\xff\xff\xff";
	$gif_frame = "\x21\xF9\x04\x00\x0a\x00\x00\x00" . "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00" . "\x02\x02\x44\x01\x00";
	$static    = $gif_head . $gif_frame . "\x3B";
	$animated  = $gif_head . "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00" . $gif_frame . $gif_frame . $gif_frame . "\x3B";
	// Cabecera de un WebP animado (RIFF/WEBP con VP8X y ANIM) y de uno estático.
	$webp_anim = 'RIFF' . pack( 'V', 40 ) . 'WEBPVP8X' . pack( 'V', 10 ) . "\x02\x00\x00\x00\x00\x00\x00\x00\x00\x00" . 'ANIM' . pack( 'V', 6 ) . "\xff\xff\xff\xff\x00\x00" . 'ANMF';
	$webp_flat = 'RIFF' . pack( 'V', 20 ) . 'WEBPVP8 ' . str_repeat( "\0", 12 );
	$add = static function ( $id, $name, $data, $mime ) use ( $dir ) {
		file_put_contents( $dir . '/' . $name, $data );
		$GLOBALS['files'][ $id ] = $dir . '/' . $name;
		$GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'post_mime_type' => $mime, 'post_title' => $name );
		$GLOBALS['amd'][ $id ]   = array( 'file' => 'up/' . $name, 'width' => 1, 'height' => 1, 'sizes' => array() );
		$GLOBALS['meta'][ $id ]  = array();
	};

	// 1. Fotogramas y detección del WebP animado.
	$add( 1, 'static.gif', $static, 'image/gif' );
	$add( 2, 'anim.gif', $animated, 'image/gif' );
	file_put_contents( $dir . '/a.webp', $webp_anim );
	file_put_contents( $dir . '/f.webp', $webp_flat );
	check( 1 === Media_WebP::gif_frames( $dir . '/static.gif' ) && 3 === Media_WebP::gif_frames( $dir . '/anim.gif' ), 'Se cuentan los fotogramas del GIF (1 y 3).' );
	check( Media_WebP::is_animated_webp( $dir . '/a.webp' ) && ! Media_WebP::is_animated_webp( $dir . '/f.webp' ) && ! Media_WebP::is_animated_webp( $dir . '/static.gif' ), 'Un WebP animado se reconoce por su bloque ANIM.' );

	// 2. GIF estático: por el editor, como un PNG, con sus tamaños y calidad 75.
	$GLOBALS['editor'] = new Fake_Editor();
	$r = Media_WebP::convert( 1, $dir . '/static.gif' );
	check( 75 === $GLOBALS['editor']->quality && 'image/webp' === $GLOBALS['posts'][1]->post_mime_type && $dir . '/static.webp' === $GLOBALS['files'][1] && 'up/static.webp' === $GLOBALS['amd'][1]['file'] && isset( $GLOBALS['amd'][1]['sizes']['thumbnail'] ), 'El GIF estático pasa a WebP con sus tamaños: ' . $r['message'] );
	check( $dir . '/static.webp' === $GLOBALS['meta'][1][ Media_WebP::META_DONE ] && 'static.webp' === $GLOBALS['posts'][1]->post_title && is_file( $dir . '/static.gif' ), 'Queda anotado, el título sigue al archivo y el GIF original se conserva.' );
	check( 'webp' === Media_WebP::optimized_kind( 1 ), 'Estado: WebP.' );

	// 3. GIF animado sin forma de crear WebP animado (este php-wasm no lo escribe): se conserva y queda revisado.
	$GLOBALS['editor'] = new Fake_Editor();
	$r = Media_WebP::convert( 2, $dir . '/anim.gif' );
	check( 'image/gif' === $GLOBALS['posts'][2]->post_mime_type && $dir . '/anim.gif' === $GLOBALS['files'][2] && $dir . '/anim.gif' === $GLOBALS['meta'][2][ Media_WebP::META_SEEN ] && ! isset( $GLOBALS['meta'][2][ Media_WebP::META_DONE ] ) && ! isset( $GLOBALS['meta'][2][ Media_WebP::META_ERROR ] ) && ! file_exists( $dir . '/anim.webp' ), 'Sin WebP animado, el GIF se conserva sin error, queda como visto y no como convertido: ' . $r['message'] );
	check( false !== strpos( $r['message'], 'se conserva' ) && 'gif' === Media_WebP::optimized_kind( 2 ), 'El mensaje lo explica y el estado es «Revisado · GIF animado».' );

	// 4. GIF animado con un plugin que sí lo escribe (filtro): WebP animado entero, sin tamaños, dimensiones del GIF.
	$add( 3, 'loop.gif', $animated, 'image/gif' );
	$GLOBALS['filters']['digitalisimo_tools_animated_webp'] = static function ( $written, $file, $output, $quality ) use ( $webp_anim ) { check( 75 === $quality, 'El filtro recibe la calidad.' ); file_put_contents( $output, $webp_anim ); return true; };
	check( Media_WebP::can_animate(), 'Con el filtro, el servidor puede crear WebP animado.' );
	$r = Media_WebP::convert( 3, $dir . '/loop.gif' );
	check( 'image/webp' === $GLOBALS['posts'][3]->post_mime_type && $dir . '/loop.webp' === $GLOBALS['files'][3] && array() === $GLOBALS['amd'][3]['sizes'] && 1 === $GLOBALS['amd'][3]['width'] && 1 === $GLOBALS['amd'][3]['height'] && 'up/loop.webp' === $GLOBALS['amd'][3]['file'] && strlen( $webp_anim ) === $GLOBALS['amd'][3]['filesize'], 'El WebP animado se aplica entero, sin tamaños reducidos y con las dimensiones: ' . $r['message'] );
	check( false !== strpos( $r['message'], 'WebP animado' ) && is_file( $dir . '/loop.gif' ) && 'webp' === Media_WebP::optimized_kind( 3 ), 'El mensaje lo dice y el GIF original se conserva.' );
	// Un resultado sin ANIM (se quedó quieto) o que no ahorra se descarta y el GIF se conserva.
	$add( 4, 'flat.gif', $animated, 'image/gif' );
	$GLOBALS['filters']['digitalisimo_tools_animated_webp'] = static function ( $written, $file, $output ) use ( $webp_flat ) { file_put_contents( $output, $webp_flat ); return true; };
	$r = Media_WebP::convert( 4, $dir . '/flat.gif' );
	check( 'image/gif' === $GLOBALS['posts'][4]->post_mime_type && ! file_exists( $dir . '/flat.webp' ) && $dir . '/flat.gif' === $GLOBALS['meta'][4][ Media_WebP::META_SEEN ], 'Un WebP que perdió la animación se descarta y el GIF se conserva: ' . $r['message'] );
	$add( 5, 'big.gif', $animated, 'image/gif' );
	$GLOBALS['filters']['digitalisimo_tools_animated_webp'] = static function ( $written, $file, $output ) use ( $webp_anim ) { file_put_contents( $output, $webp_anim . str_repeat( 'x', 500 ) ); return true; };
	$r = Media_WebP::convert( 5, $dir . '/big.gif' );
	check( 'image/gif' === $GLOBALS['posts'][5]->post_mime_type && ! file_exists( $dir . '/big.webp' ), 'Un WebP animado que pesa más se descarta.' );
	unset( $GLOBALS['filters']['digitalisimo_tools_animated_webp'] );

	// 5. SVG: minimizado junto al original y anotado; uno ya mínimo queda revisado sin escribir nada.
	$svg = '<?xml version="1.0"?><!-- editor --><svg xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" inkscape:version="1"><circle r="1"/></svg>';
	$add( 6, 'logo.svg', $svg, 'image/svg+xml' );
	$r = Media_WebP::convert( 6, $dir . '/logo.svg' );
	check( $dir . '/logo.min.svg' === $GLOBALS['files'][6] && $dir . '/logo.min.svg' === $GLOBALS['meta'][6][ Media_WebP::META_DONE ] && $dir . '/logo.min.svg' === $GLOBALS['meta'][6][ Media_WebP::META_SEEN ] && '<svg xmlns="http://www.w3.org/2000/svg"><circle r="1"/></svg>' === file_get_contents( $dir . '/logo.min.svg' ) && 'image/svg+xml' === $GLOBALS['posts'][6]->post_mime_type && 'svg' === Media_WebP::optimized_kind( 6 ), 'El SVG se minimiza, se aplica y queda como «Optimizado · SVG»: ' . $r['message'] );
	$add( 7, 'min.svg', '<svg xmlns="http://www.w3.org/2000/svg"><circle r="1"/></svg>', 'image/svg+xml' );
	$r = Media_WebP::convert( 7, $dir . '/min.svg' );
	check( $dir . '/min.svg' === $GLOBALS['meta'][7][ Media_WebP::META_SEEN ] && ! isset( $GLOBALS['meta'][7][ Media_WebP::META_DONE ] ) && ! file_exists( $dir . '/min.min.svg' ) && 'svg' === Media_WebP::optimized_kind( 7 ), 'Un SVG ya mínimo queda visto, sin archivo nuevo, y se muestra como optimizado porque lo está.' );

	// 6. Reversión: si el adjunto no se puede actualizar, todo vuelve a como estaba y el WebP se borra.
	$add( 8, 'foto.png', 'png', 'image/png' );
	$GLOBALS['editor'] = new Fake_Editor();
	$GLOBALS['refuse_update'] = true;
	$r = Media_WebP::convert( 8, $dir . '/foto.png' );
	check( 'image/png' === $GLOBALS['posts'][8]->post_mime_type && $dir . '/foto.png' === $GLOBALS['files'][8] && ! file_exists( $dir . '/foto.webp' ) && isset( $GLOBALS['meta'][8][ Media_WebP::META_ERROR ] ) && 'up/foto.png' === $GLOBALS['amd'][8]['file'], 'Si falla, el adjunto queda intacto y el WebP se borra: ' . $r['message'] );
	$GLOBALS['refuse_update'] = false;
	$GLOBALS['editor'] = null;
	$r = Media_WebP::convert( 8, $dir . '/foto.png' );
	check( 'Sin editor.' === $GLOBALS['meta'][8][ Media_WebP::META_ERROR ], 'Sin editor de imágenes, el error se anota.' );
	check( 'No se encontró el archivo original.' === Media_WebP::convert( 9, $dir . '/nada.png' )['message'] || false !== strpos( Media_WebP::convert( 9, $dir . '/nada.png' )['message'], 'No se encontró' ), 'Sin archivo, aviso.' );

	// 7. Estados de la Biblioteca.
	check( array( 'digitalisimo_tools_webp' => 'Optimizada · WebP' ) === Media_WebP::media_states( array(), (object) array( 'ID' => 1 ) ) && array( 'digitalisimo_tools_webp' => 'Revisado · GIF animado' ) === Media_WebP::media_states( array(), (object) array( 'ID' => 2 ) ) && array( 'digitalisimo_tools_webp' => 'Optimizado · SVG' ) === Media_WebP::media_states( array(), (object) array( 'ID' => 6 ) ) && array() === Media_WebP::media_states( array(), (object) array( 'ID' => 8 ) ), 'Cada tipo lleva su estado en Medios.' );
	$data = Media_WebP::attachment_data( array(), (object) array( 'ID' => 6 ) );
	check( true === $data['digitalisimoOptimized'] && 'Optimizado · SVG' === $data['digitalisimoOptimizedLabel'] && false === Media_WebP::attachment_data( array(), (object) array( 'ID' => 2 ) )['digitalisimoOptimized'], 'La insignia de Medios lleva la etiqueta de cada tipo y no marca un GIF conservado.' );
	check( in_array( 'image/gif', Media_WebP::SOURCES, true ) && in_array( 'image/svg+xml', Media_WebP::SOURCES, true ) && array( 'image/jpeg', 'image/png' ) === Media_WebP::TO_WEBP && array( 'image/gif', 'image/svg+xml' ) === Media_WebP::KEEPABLE, 'GIF y SVG entran en el lote; JPG y PNG siguen volviendo a procesarse si una versión anterior los marcó sin convertir.' );
	// El GIF animado conservado y el WebP animado llevan la marca de visto; un JPG convertido, sólo la de WebP.
	check( $dir . '/loop.webp' === $GLOBALS['meta'][3][ Media_WebP::META_SEEN ] && ! isset( $GLOBALS['meta'][1][ Media_WebP::META_SEEN ] ) && ! isset( $GLOBALS['meta'][8][ Media_WebP::META_SEEN ] ), 'Marcas por tipo: el GIF que pasó a WebP animado lleva la de visto; un JPG/PNG convertido o fallido, no.' );

	foreach ( glob( $dir . '/*' ) as $f ) unlink( $f );
	rmdir( $dir ); unlink( $abspath . 'wp-admin/includes/image.php' ); rmdir( $abspath . 'wp-admin/includes' ); rmdir( $abspath . 'wp-admin' ); rmdir( $abspath );
	echo "DIGITALÍSIMO Tools: GIF estático a WebP, GIF animado a WebP animado entero o conservado, SVG minimizado, reversión y estados de Medios en verde.\n";
}
