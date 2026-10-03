<?php
/**
 * Favicon: validación del PNG maestro, favicon.ico multi-tamaño, regeneración
 * sólo cuando cambia la imagen, URLs estables, etiquetas y aislamiento por sitio.
 * El editor de imágenes de WordPress se simula: escribe PNG falsos con su lado.
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['blog'] = 1;
$GLOBALS['options'] = array();
$GLOBALS['settings'] = array( 1 => array(), 2 => array() );
$GLOBALS['tmp'] = sys_get_temp_dir() . '/digitalisimo-favicon-test-' . getmypid();
$GLOBALS['editor_calls'] = 0;
@mkdir( $GLOBALS['tmp'], 0777, true );

class WP_Error { private $m; function __construct( $c = '', $m = '' ) { $this->m = $m; } function get_error_message() { return $this->m; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
class Digitalisimo_Integrations_Settings { const OPTION = 'seo'; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { return $GLOBALS['settings'][ $GLOBALS['blog'] ][ $key ] ?? ''; } }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $GLOBALS['blog'] ][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $GLOBALS['blog'] ][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $GLOBALS['blog'] ][ $key ] ); return true; }
function trailingslashit( $v ) { return rtrim( $v, '/' ) . '/'; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['tmp'] . '/sites/' . $GLOBALS['blog'], 'baseurl' => 'https://site' . $GLOBALS['blog'] . '.test/wp-content/uploads' ); }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function esc_url( $v ) { return $v; }
function home_url( $path = '' ) { return 'https://site' . $GLOBALS['blog'] . '.test' . $path; }
function get_current_blog_id() { return $GLOBALS['blog']; }
function attachment_url_to_postid( $url ) { return isset( $GLOBALS['masters'][ $url ] ) ? 7 : 0; }
function get_attached_file( $id ) { return $GLOBALS['master_path']; }
function wp_getimagesize( $path ) { return $GLOBALS['master_info']; }

/** Editor falso: «PNG» de bytes conocidos con el lado como contenido. */
class Fake_Editor {
	private $size;
	function __construct( $size ) { $this->size = $size; }
	function get_size() { return array( 'width' => $this->size, 'height' => $this->size ); }
	function resize( $w, $h, $crop ) { if ( $w >= $this->size ) return new WP_Error( 'x', 'sin cambios' ); $this->size = $w; return true; }
	function save( $file, $mime ) { file_put_contents( $file, "\x89PNG" . str_repeat( 'x', $this->size ) ); return array( 'path' => $file ); }
}
function fake_editor( $path ) { ++$GLOBALS['editor_calls']; return new Fake_Editor( (int) $GLOBALS['master_info'][0] ); }

require __DIR__ . '/../digitalisimo-seo/includes/class-favicon.php';
$f = Digitalisimo_Integrations_Favicon::class;
function check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function master( $content, $side = 512, $mime = 'image/png' ) {
	$GLOBALS['master_path'] = $GLOBALS['tmp'] . '/master-' . $GLOBALS['blog'] . '.png';
	file_put_contents( $GLOBALS['master_path'], $content );
	$GLOBALS['master_info'] = array( $side, $side, 'mime' => $mime );
	$url = 'https://site' . $GLOBALS['blog'] . '.test/wp-content/uploads/master.png';
	$GLOBALS['masters'][ $url ] = true;
	$GLOBALS['settings'][ $GLOBALS['blog'] ][ Digitalisimo_Integrations_Favicon::KEY ] = $url;
	return $url;
}
function sync_with_fake() { return Digitalisimo_Integrations_Favicon::sync( false, 'fake_editor' ); }

// Validación del PNG maestro.
check( '' === $f::validate( array( 512, 512, 'mime' => 'image/png' ) ) && '' === $f::validate( array( 1024, 1024, 'mime' => 'image/png' ) ), 'PNG cuadrado de 512 o más sirve.' );
check( false !== strpos( $f::validate( array( 512, 512, 'mime' => 'image/jpeg' ) ), 'PNG' ), 'Otro formato se rechaza.' );
check( false !== strpos( $f::validate( array( 600, 512, 'mime' => 'image/png' ) ), 'cuadrada' ), 'No cuadrada se rechaza.' );
check( false !== strpos( $f::validate( array( 256, 256, 'mime' => 'image/png' ) ), 'al menos 512' ), 'Menor de 512 se rechaza.' );
check( '' !== $f::validate( false ), 'Imagen ilegible.' );

// favicon.ico: cabecera ICO, tres entradas 16/32/48 con PNG dentro y offsets correctos.
$ico = $f::ico( array( 48 => 'C48', 16 => 'A16', 32 => 'B32' ) );
$head = unpack( 'vreserved/vtype/vcount', substr( $ico, 0, 6 ) );
check( 0 === $head['reserved'] && 1 === $head['type'] && 3 === $head['count'], 'Cabecera ICO válida.' );
$offset = 6 + 16 * 3;
foreach ( array( 16 => 'A16', 32 => 'B32', 48 => 'C48' ) as $i => $bytes ) {
	$index = array_search( $i, array( 16, 32, 48 ), true );
	$entry = unpack( 'Cw/Ch/Ccolors/Creserved/vplanes/vbits/Vsize/Voffset', substr( $ico, 6 + 16 * $index, 16 ) );
	check( $i === $entry['w'] && $i === $entry['h'] && 32 === $entry['bits'] && strlen( $bytes ) === $entry['size'] && $offset === $entry['offset'] && $bytes === substr( $ico, $entry['offset'], $entry['size'] ), 'Entrada ICO de ' . $i . ' px correcta y ordenada.' );
	$offset += strlen( $bytes );
}
check( 0 === unpack( 'C', substr( $f::ico( array( 256 => 'X' ) ), 6, 1 ) )[1], 'Un lado de 256 se escribe como 0, según el formato.' );

// Generación con el editor: todos los archivos, del tamaño correcto y sin temporales.
master( 'imagen-original' );
check( sync_with_fake() && 7 === $GLOBALS['editor_calls'], 'Se abre el editor una vez por tamaño (16, 32, 48, 96, 180, 192, 512).' );
$dir = wp_upload_dir()['basedir'] . '/digitalisimo-favicon';
foreach ( $f::FILES as $file => $size ) check( "\x89PNG" . str_repeat( 'x', $size ) === file_get_contents( $dir . '/' . $file ), $file . ' mide ' . $size . ' px.' );
$ico = file_get_contents( $dir . '/favicon.ico' );
check( 3 === unpack( 'v', substr( $ico, 4, 2 ) )[1] && false !== strpos( $ico, "\x89PNG" . str_repeat( 'x', 16 ) ), 'favicon.ico contiene 16, 32 y 48 px.' );
check( ! glob( $dir . '/*.tmp.png' ), 'No quedan temporales.' );
check( $f::active(), 'Activo tras generar.' );

// URLs estables con versión según el contenido; etiquetas completas.
$tags = implode( "\n", $f::tags() );
$version = $f::state()['version'];
check( false !== strpos( $tags, 'href="https://site1.test/wp-content/uploads/digitalisimo-favicon/favicon.ico?v=' . $version . '" sizes="16x16 32x32 48x48"' ), 'favicon.ico declarado con sus tamaños.' );
foreach ( array( 48, 96, 192, 512 ) as $size ) check( false !== strpos( $tags, 'type="image/png" sizes="' . $size . 'x' . $size . '" href="https://site1.test/wp-content/uploads/digitalisimo-favicon/favicon-' . $size . 'x' . $size . '.png?v=' ), 'PNG de ' . $size . ' declarado.' );
check( false !== strpos( $tags, '<link rel="apple-touch-icon" sizes="180x180" href="https://site1.test/wp-content/uploads/digitalisimo-favicon/apple-touch-icon.png?v=' ) && 8 === count( $f::tags() ), 'apple-touch-icon, manifiesto y msapplication: ocho etiquetas.' );

// Supremo: todo lo que pide el icono del sitio recibe éste, en el tamaño adecuado.
check( 'favicon-48x48.png' === $f::file_for( 32 ) && 'favicon-48x48.png' === $f::file_for( 48 ) && 'apple-touch-icon.png' === $f::file_for( 180 ) && 'favicon-192x192.png' === $f::file_for( 192 ) && 'favicon-512x512.png' === $f::file_for( 270 ) && 'favicon-512x512.png' === $f::file_for( 512 ), 'Archivo más cercano por arriba al tamaño pedido.' );
check( 0 === strpos( $f::site_icon_url( 'https://site1.test/wp-content/uploads/viejo.png', 32, 1 ), 'https://site1.test/wp-content/uploads/digitalisimo-favicon/favicon-48x48.png?v=' ), 'get_site_icon_url devuelve el favicon generado.' );
check( 'otro' === $f::site_icon_url( 'otro', 32, 2 ), 'Una consulta por otro sitio de la red no se toca.' );
check( $f::tags() === $f::meta_tags( array( '<link rel="icon" href="viejo.png" sizes="32x32">' ) ), 'wp_site_icon() imprime sólo este juego.' );
$head = "<html><head><title>x</title>\n<link rel=\"shortcut icon\" href=\"/tema/favicon.ico\">\n<link rel='apple-touch-icon' href=\"/kit/apple.png\">\n<link rel=\"stylesheet\" href=\"a.css\">\n<meta name=\"msapplication-TileImage\" content=\"/viejo.png\">\n" . implode( "\n", $f::tags() ) . "\n</head><body><a rel=\"icon\" href=\"/body.png\">x</a></body></html>";
$clean = $f::clean_head( $head, $f::tags() );
check( false === strpos( $clean, '/tema/favicon.ico' ) && false === strpos( $clean, '/kit/apple.png' ) && false === strpos( $clean, '/viejo.png' ), 'Los iconos del tema, kit o plugins se retiran del head.' );
check( 1 === substr_count( $clean, 'favicon.ico?v=' ) && false !== strpos( $clean, 'a.css' ) && false !== strpos( $clean, '/body.png' ), 'Se conserva el nuestro, el resto del head y el body intactos.' );
$bare = $f::clean_head( '<html><head><link rel="icon" href="/tema.png"></head><body></body></html>', $f::tags() );
check( 1 === substr_count( $bare, 'digitalisimo-favicon/favicon.ico' ) && false === strpos( $bare, '/tema.png' ) && false !== strpos( $bare, 'rel="manifest"' ), 'Si un tema quitó wp_site_icon(), se insertan las etiquetas.' );
$pwa = $f::clean_head( '<html><head><link rel="manifest" href="/pwa/manifest.json">' . implode( '', $f::tags() ) . '</head><body></body></html>', $f::tags() );
check( false !== strpos( $pwa, '/pwa/manifest.json' ) && false === strpos( $pwa, 'site.webmanifest' ), 'Un manifiesto de otro plugin (PWA) se respeta y no se duplica.' );
check( '<p>sin head</p>' === $f::clean_head( '<p>sin head</p>', $f::tags() ), 'Sin </head> no se toca.' );
$manifest = json_decode( $f::manifest( 'DIGITALÍSIMO Agencia', 'https://site1.test/' ), true );
check( 'DIGITALÍSIMO Agencia' === $manifest['name'] && 'DIGITALÍSIMO' === $manifest['short_name'] && 2 === count( $manifest['icons'] ) && '512x512' === $manifest['icons'][1]['sizes'] && 0 === strpos( $manifest['icons'][0]['src'], 'https://site1.test/wp-content/uploads/digitalisimo-favicon/favicon-192x192.png' ), 'Manifiesto con nombre e iconos 192/512.' );

// Regenerar sólo cuando cambia la imagen.
check( ! sync_with_fake() && 7 === $GLOBALS['editor_calls'], 'Mismo PNG: no se regenera.' );
master( 'imagen-nueva' );
check( sync_with_fake() && 14 === $GLOBALS['editor_calls'] && $version !== $f::state()['version'], 'Otro contenido en la misma URL: se regenera y cambia la versión, no la ruta.' );

// Un PNG inválido deja el error y no activa el favicon.
master( 'pequeña', 256 );
sync_with_fake();
check( ! $f::active() && false !== strpos( $f::state()['error'], 'al menos 512' ), 'Un PNG inválido se informa y no se publica.' );

// Multisite: cada sitio genera en sus propias uploads y con su propio estado.
$GLOBALS['blog'] = 2;
check( ! $f::active() && array() === $f::state(), 'El sitio 2 no hereda el favicon del sitio 1.' );
master( 'logo-sitio-2', 1024 );
sync_with_fake();
check( $f::active() && is_file( $GLOBALS['tmp'] . '/sites/2/digitalisimo-favicon/favicon.ico' ) && false !== strpos( implode( '', $f::tags() ), 'https://site2.test/' ), 'El sitio 2 genera los suyos con sus URLs.' );
check( "\x89PNG" . str_repeat( 'x', 512 ) === file_get_contents( $GLOBALS['tmp'] . '/sites/2/digitalisimo-favicon/favicon-512x512.png' ), 'Desde 1024 px se reduce a 512.' );

// Quitar la imagen borra los archivos generados y devuelve el control al icono de WordPress.
$GLOBALS['settings'][2][ $f::KEY ] = '';
$f::sync();
check( ! $f::active() && ! is_file( $GLOBALS['tmp'] . '/sites/2/digitalisimo-favicon/favicon.ico' ) && array() === $f::state(), 'Sin imagen no queda nada publicado.' );

// Iconos ajenos en la portada (tema, plugin o caché).
$html = '<link rel="icon" href="https://x.test/wp-content/uploads/digitalisimo-favicon/favicon.ico?v=1"><link rel="shortcut icon" href="/theme/favicon.png"><link rel=\'apple-touch-icon\' href="https://x.test/tema/apple.png"><link rel="stylesheet" href="a.css">';
check( array( '/theme/favicon.png', 'https://x.test/tema/apple.png' ) === $f::foreign_icons( $html ), 'Se detectan sólo los iconos que no son de Digitalísimo.' );

array_map( 'unlink', array_filter( glob( $GLOBALS['tmp'] . '/{,*/,*/*/,*/*/*/}*', GLOB_BRACE ) ?: array(), 'is_file' ) );
echo "Favicon: validación, ICO multi-tamaño, regeneración por contenido, etiquetas y aislamiento por sitio correctos.\n";
