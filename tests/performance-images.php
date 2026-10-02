<?php
/**
 * Imágenes responsive: atributos, vinculación con adjuntos, exclusiones,
 * prioridad de carga, sizes desde el layout de Elementor, fondos y aislamiento
 * por sitio. Sin WordPress completo: las funciones de medios se simulan.
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['blog'] = 1;
$GLOBALS['settings'] = array();

class Digitalisimo_Integrations_Settings { const OPTION = 'seo'; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { return $GLOBALS['settings'][ $GLOBALS['blog'] ][ $key ] ?? ''; } }
class Digitalisimo_Integrations_Performance_Manager { public static function frontend_safe() { return true; } }
class Digitalisimo_Integrations_Performance_Cache {
	public static $store = array();
	public static function get( $name, &$found = null ) { $key = $GLOBALS['blog'] . ':' . $name; $found = array_key_exists( $key, self::$store ); return $found ? self::$store[ $key ] : false; }
	public static function set( $name, $value ) { self::$store[ $GLOBALS['blog'] . ':' . $name ] = $value; return true; }
}
function trailingslashit( $v ) { return rtrim( $v, '/' ) . '/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function is_ssl() { return true; }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $v ) { return $v; }
function wp_upload_dir() { return array( 'baseurl' => 'https://site' . $GLOBALS['blog'] . '.test/wp-content/uploads', 'basedir' => '/tmp' ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $GLOBALS['blog'] ][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $GLOBALS['blog'] ][ $key ] = $value; return true; }
function wp_using_ext_object_cache() { return false; }
function get_post_mime_type( $id ) { return 99 === $id ? 'image/svg+xml' : 'image/jpeg'; }
function get_attached_file( $id ) { return '/tmp/x.jpg'; }
function wp_getimagesize( $file ) { return false; }
// Adjunto 10 en el sitio 1: original 1920×1080 con cuatro tamaños registrados.
function wp_get_attachment_metadata( $id ) {
	if ( 10 !== $id || 1 !== $GLOBALS['blog'] ) return false;
	return array( 'width' => 1920, 'height' => 1080, 'file' => '2026/01/hero.jpg', 'sizes' => array(
		'medium' => array( 'file' => 'hero-300x169.jpg', 'width' => 300, 'height' => 169 ),
		'medium_large' => array( 'file' => 'hero-768x432.jpg', 'width' => 768, 'height' => 432 ),
		'large' => array( 'file' => 'hero-1024x576.jpg', 'width' => 1024, 'height' => 576 ),
		'1536x1536' => array( 'file' => 'hero-1536x864.jpg', 'width' => 1536, 'height' => 864 ),
	) );
}
function attachment_url_to_postid( $url ) { $GLOBALS['lookups'] = ( $GLOBALS['lookups'] ?? 0 ) + 1; return 'https://site1.test/wp-content/uploads/2026/01/hero.jpg' === $url ? 10 : 0; }
function wp_calculate_image_srcset( $size, $src, $meta, $id ) {
	$base = 'https://site1.test/wp-content/uploads/2026/01/';
	return $base . 'hero-300x169.jpg 300w, ' . $base . 'hero-768x432.jpg 768w, ' . $base . 'hero-1024x576.jpg 1024w, ' . $base . 'hero-1536x864.jpg 1536w, ' . $base . 'hero.jpg 1920w';
}
function wp_calculate_image_sizes( $size ) { return '(max-width: ' . $size[0] . 'px) 100vw, ' . $size[0] . 'px'; }

require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-images.php';
$img = Digitalisimo_Integrations_Performance_Images::class;
function check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function on( $extra = array() ) { $GLOBALS['settings'][ $GLOBALS['blog'] ] = $extra + array( 'perf_img_enabled' => 1, 'perf_img_srcset' => 1, 'perf_img_sizes' => 1, 'perf_img_dimensions' => 1, 'perf_img_lazy' => 1, 'perf_img_backgrounds' => 0, 'perf_img_mode' => 'safe', 'perf_img_exclusions' => '' ); }
function page( $body ) { return "<!doctype html><html><head></head><body>$body</body></html>"; }
on();
$hero = 'https://site1.test/wp-content/uploads/2026/01/hero.jpg';

// Atributos: el primero (src) también se lee, con comillas simples, sin comillas y booleanos.
$attrs = $img::attributes( "<img src=\"$hero\" class='a b' width=300 alt=\"&quot;x&quot;\" hidden>" );
check( $hero === $attrs['src'] && 'a b' === $attrs['class'] && '300' === $attrs['width'] && '"x"' === $attrs['alt'] && array_key_exists( 'hidden', $attrs ), 'El parser debe leer todos los atributos.' );

// Un slide de Elementor (sin srcset) se vuelve responsive sin tocar sus clases ni su src.
$out = $img::process( page( "<img class=\"swiper-slide-image\" src=\"$hero\" alt=\"Slide\">" ) );
$tag = $img::attributes( preg_match( '/<img[^>]*>/', $out, $m ) ? $m[0] : '' );
check( 'swiper-slide-image' === $tag['class'] && $hero === $tag['src'], 'Clases y src del slide se conservan.' );
check( 5 === count( explode( ',', $tag['srcset'] ) ) && '(max-width: 1920px) 100vw, 1920px' === $tag['sizes'], 'Se añade srcset con las variantes existentes y el sizes de WordPress.' );
check( '1920' === $tag['width'] && '1080' === $tag['height'], 'Se añaden width y height del archivo.' );
$report = $img::probe_report( array() );

// Un tamaño intermedio se vincula a su original y usa sus propias dimensiones.
$out = $img::process( page( '<img src="https://site1.test/wp-content/uploads/2026/01/hero-1024x576.jpg">' ) );
check( false !== strpos( $out, 'width="1024" height="576"' ), 'Un tamaño intermedio usa sus dimensiones, no las del original.' );
$mid = $img::attributes( preg_match( '/<img[^>]*>/', $out, $m ) ? $m[0] : '' );
check( 3 === count( explode( ',', $mid['srcset'] ) ) && false === strpos( $mid['srcset'], '1536w' ) && false === strpos( $mid['srcset'], '1920w' ), 'Con un tamaño elegido de 1024 px el srcset no ofrece archivos mayores.' );
check( '' === $img::cap_srcset( 'a.jpg 300w, b.jpg 768w', 300 ) && '' === $img::cap_srcset( 'a.jpg 1x, b.jpg 2x', 2000 ), 'Con menos de dos candidatos, o descriptores que no son de ancho, no hay srcset.' );

// Lo que ya está completo no se toca.
$done = "<img src=\"$hero\" srcset=\"$hero 1920w\" sizes=\"50vw\" width=\"1920\" height=\"1080\" loading=\"lazy\">";
check( page( $done ) === $img::process( page( $done ) ), 'Una imagen ya responsive no se modifica ni se duplica.' );

// Lo que no se puede vincular o no conviene tocar queda igual, byte a byte.
$untouched = array(
	'<img src="https://cdn.otro.test/foto.jpg">',
	'<img src="https://otro.test/wp-content/uploads/2026/01/hero.jpg">',
	'<img src="https://site1.test/wp-content/uploads/logo.svg">',
	'<img src="data:image/png;base64,AAAA">',
	'<img src="https://tracker.test/pixel.png" width="1" height="1">',
	'<img class="otra-libreria" data-src="' . $hero . '">',
	'<img src="' . $hero . '" data-no-optimize="1">',
	'<img class="emoji" src="' . $hero . '">',
	'<img src="https://site1.test/wp-content/uploads/2026/01/desconocida.jpg">',
);
foreach ( $untouched as $html ) check( page( $html ) === $img::process( page( $html ) ), 'Debe quedar intacta: ' . $html );

// Dentro de script, noscript, template o picture, un <img> no es una imagen a optimizar.
$blocks = "<script>var t='<img src=\"$hero\">';</script><noscript><img src=\"$hero\"></noscript><picture><source srcset=\"a.webp\"><img src=\"$hero\"></picture>";
check( page( $blocks ) === $img::process( page( $blocks ) ), 'Los bloques especiales se restauran intactos.' );

// Carrusel de Elementor con carga diferida de Swiper: sin src, con data-src.
$swiper = '<img class="swiper-slide-image swiper-lazy" data-src="https://site1.test/wp-content/uploads/2026/01/hero-1024x576.jpg" alt="Slide" data-digitalisimo-sizes="(max-width: 1024px) 100vw, 50vw">';
$out = $img::process( page( $swiper ) );
$tag = $img::attributes( preg_match( '/<img[^>]*>/', $out, $m ) ? $m[0] : '' );
check( ! isset( $tag['src'] ) && 'https://site1.test/wp-content/uploads/2026/01/hero-1024x576.jpg' === $tag['data-src'] && 'swiper-slide-image swiper-lazy' === $tag['class'], 'El mecanismo de Swiper (data-src, clases) no se toca.' );
check( 3 === count( explode( ',', $tag['data-srcset'] ) ) && false === strpos( $tag['data-srcset'], '1536w' ) && '(max-width: 1024px) 100vw, 50vw' === $tag['data-sizes'], 'Swiper recibe data-srcset acotado al tamaño elegido y data-sizes del layout.' );
check( ! isset( $tag['srcset'] ) && ! isset( $tag['loading'] ) && ! isset( $tag['fetchpriority'] ) && ! isset( $tag['data-digitalisimo-sizes'] ), 'Sin srcset ni prioridad: Swiper decide cuándo cargar.' );
check( '1024' === $tag['width'] && '576' === $tag['height'], 'El slide reserva su espacio con las dimensiones del archivo que cargará.' );
$sized = '<img class="swiper-slide-image swiper-lazy" data-src="https://site1.test/wp-content/uploads/2026/01/hero-1024x576.jpg" width="600">';
check( 1 === substr_count( $img::process( page( $sized ) ), 'width=' ) && false === strpos( $img::process( page( $sized ) ), 'height=' ), 'Un slide con dimensiones propias no se toca.' );
$report = ( new ReflectionProperty( $img, 'report' ) )->getValue();
check( 'RESPONSIVE' === $report[0]['status'] && in_array( 'LAZY', $report[0]['states'], true ) && '' !== $report[0]['srcset'], 'El informe muestra el slide como responsive y diferido.' );
$lazysizes = '<img class="lazyload" data-src="' . $hero . '" data-sizes="auto">';
check( false !== strpos( $img::process( page( $lazysizes ) ), 'data-sizes="auto"' ) && 1 === substr_count( $img::process( page( $lazysizes ) ), 'data-sizes=' ), 'Un data-sizes existente de lazysizes se respeta.' );

// Exclusiones manuales: clase, id de elemento, adjunto y URL.
foreach ( array( '.no-tocar' => '<img class="x no-tocar" src="' . $hero . '">', '#portada' => '<img id="portada" src="' . $hero . '">', 'id:10' => '<img src="' . $hero . '">', '2026/01/hero' => '<img src="' . $hero . '">' ) as $rule => $html ) {
	on( array( 'perf_img_exclusions' => $rule ) );
	check( page( $html ) === $img::process( page( $html ) ), 'La exclusión manual ' . $rule . ' debe respetarse.' );
}
check( ".a\n#b\nid:12\n[data-x]\n2026/01/foto" === $img::sanitize_exclusions( ".a\n#b\nid:12\n[data-x]\n2026/01/foto\n<script>\n" ), 'Las exclusiones aceptan sólo formas simples y seguras.' );
on();

// Prioridad: logo y primeras imágenes sin diferir; un único fetchpriority; el resto lazy.
$body = '<img class="custom-logo" src="' . $hero . '" width="200" height="60">'
	. '<img src="' . $hero . '" width="40" height="30">'
	. str_repeat( '<img src="' . $hero . '">', 5 );
$out = $img::process( page( $body ) );
preg_match_all( '/<img[^>]*>/', $out, $tags );
$all = array_map( array( $img, 'attributes' ), $tags[0] );
check( ! isset( $all[0]['loading'] ) && ! isset( $all[0]['fetchpriority'] ), 'El logo no se difiere ni compite por prioridad.' );
check( ! isset( $all[1]['loading'] ), 'Una imagen pequeña de cabecera no se difiere ni consume el cupo del primer viewport.' );
check( 'high' === ( $all[2]['fetchpriority'] ?? '' ) && ! isset( $all[2]['loading'] ), 'La primera imagen grande es la candidata a LCP.' );
check( 1 === substr_count( $out, 'fetchpriority="high"' ), 'Sólo una imagen por página recibe fetchpriority alto.' );
check( ! isset( $all[3]['loading'] ) && ! isset( $all[4]['loading'] ) && 'lazy' === $all[5]['loading'] && 'async' === $all[5]['decoding'] && 'lazy' === $all[6]['loading'], 'Tras las tres primeras, lazy y decoding async.' );
$out = $img::process( page( '<img src="https://otro.test/a.jpg" fetchpriority="high">' . str_repeat( '<img src="' . $hero . '">', 2 ) ) );
check( 1 === substr_count( $out, 'fetchpriority="high"' ), 'Si la página ya prioriza una imagen, no se añade otra.' );
// Un lazy existente en la candidata a LCP: seguro lo respeta, estricto lo corrige.
$lazy_hero = page( '<img src="' . $hero . '" loading="lazy">' );
check( false !== strpos( $img::process( $lazy_hero ), 'loading="lazy"' ) && false === strpos( $img::process( $lazy_hero ), 'fetchpriority' ), 'Modo seguro respeta un lazy existente y no prioriza contra él.' );
on( array( 'perf_img_mode' => 'strict' ) );
$strict = $img::process( $lazy_hero );
check( false !== strpos( $strict, 'loading="eager"' ) && false !== strpos( $strict, 'fetchpriority="high"' ), 'Modo estricto quita el lazy de la candidata a LCP.' );
on();

// Logos de clientes y del pie: el nombre «logo» sólo es crítico al principio.
$out = $img::process( page( str_repeat( '<img src="' . $hero . '">', 3 ) . '<img src="' . $hero . '" alt="Logo cliente">' ) );
preg_match_all( '/<img[^>]*>/', $out, $tags );
check( 'lazy' === ( $img::attributes( $tags[0][3] )['loading'] ?? '' ), 'Un logo de cliente más abajo se difiere como cualquier imagen.' );
// Una imagen que ya declara su prioridad no ocupa el puesto de LCP.
$out = $img::process( page( '<img src="' . $hero . '" fetchpriority="low"><img src="' . $hero . '">' ) );
preg_match_all( '/<img[^>]*>/', $out, $tags );
check( 'low' === $img::attributes( $tags[0][0] )['fetchpriority'] && 'high' === ( $img::attributes( $tags[0][1] )['fetchpriority'] ?? '' ), 'La siguiente imagen grande es la candidata a LCP.' );
// Sin variantes menores: añadir dimensiones no la vuelve «responsive».
$img::process( page( '<img src="https://site1.test/wp-content/uploads/2026/01/hero-300x169.jpg">' ) );
$report = ( new ReflectionProperty( $img, 'report' ) )->getValue();
check( 'SIN VARIANTES' === $report[0]['status'] && '300' === $report[0]['width'], 'Una imagen sin variantes menores figura SIN VARIANTES aunque reciba width/height.' );

// Interruptores independientes.
on( array( 'perf_img_srcset' => 0 ) );
check( false === strpos( $img::process( page( '<img src="' . $hero . '">' ) ), 'srcset=' ), 'Sin la opción srcset no se añade.' );
on( array( 'perf_img_enabled' => 0 ) );
check( false === strpos( $img::process( page( '<img src="' . $hero . '">' ) ), 'srcset=' ), 'Con la optimización apagada no se añade nada.' );
on();

// sizes desde el layout de Elementor; la marca interna se retira.
$box  = $img::layout_entry( 'section', array( 'layout' => 'boxed' ) );
$half = $img::layout_entry( 'column', array( '_column_size' => 50 ) );
$bp   = array( 'mobile' => 767, 'tablet' => 1024 );
check( '(max-width: 1024px) 100vw, (max-width: 1140px) 50vw, 570px' === $img::sizes_from_stack( array( $box, $half ), 'safe', $bp ), 'Columna de 50 % en caja de 1140 px.' );
check( '(max-width: 767px) 100vw, (max-width: 1140px) 50vw, 570px' === $img::sizes_from_stack( array( $box, $half ), 'strict', $bp ), 'Estricto sólo declara 100vw en móvil.' );
check( '(max-width: 1300px) 100vw, 1300px' === $img::sizes_from_stack( array( $img::layout_entry( 'container', array( 'content_width' => 'boxed', 'boxed_width' => array( 'size' => 1300, 'unit' => 'px' ) ) ) ), 'safe', $bp ), 'A ancho completo en una caja de 1300 px no se repiten cláusulas.' );
check( '(max-width: 1024px) 100vw, 50vw' === $img::sizes_from_stack( array( $img::layout_entry( 'section', array( 'layout' => 'full_width' ) ), $half ), 'safe', $bp ), 'Columna de 50 % a ancho completo.' );
check( '' === $img::sizes_from_stack( array( $img::layout_entry( 'section', array( 'layout' => 'full_width' ) ), $img::layout_entry( 'column', array( '_column_size' => 100 ) ) ), 'safe', $bp ), 'Ancho completo: se usa el sizes de WordPress.' );
$container = $img::layout_entry( 'container', array( 'content_width' => 'boxed', 'boxed_width' => array( 'size' => 1200, 'unit' => 'px' ), 'flex_direction' => 'row' ) );
check( 1200.0 === (float) $container['cap'] && $container['row'], 'Un contenedor en caja aporta su ancho y su dirección.' );
$out = $img::process( page( '<img src="' . $hero . '" data-digitalisimo-sizes="(max-width: 1024px) 100vw, 570px">' ) );
check( false !== strpos( $out, 'sizes="(max-width: 1024px) 100vw, 570px"' ) && false === strpos( $out, 'data-digitalisimo-sizes' ), 'El sizes del layout se aplica y la marca interna desaparece.' );

// Fondos: nunca un archivo insuficiente para viewport × DPR, y nunca con «cover».
$variants = array( array( 'width' => 768, 'url' => 'a-768.jpg' ), array( 'width' => 1536, 'url' => 'a-1536.jpg' ), array( 'width' => 2048, 'url' => 'a-2048.jpg' ) );
check( '' === $img::background_rules( '.x', 2560, $variants, 'cover', $bp ), 'Con cover el archivo necesario depende del alto: no se toca.' );
$css = $img::background_rules( '.x', 2560, $variants, 'auto', $bp );
check( false !== strpos( $css, '(max-width:1024px) and (max-resolution:2dppx){.x{background-image:url("a-2048.jpg")}}' ) && false !== strpos( $css, '(max-width:767px) and (max-resolution:2dppx){.x{background-image:url("a-1536.jpg")}}' ), 'Tablet usa ≥2048 px y móvil ≥1534 px.' );
$css = $img::background_rules( '.x', 1920, $variants, 'auto', $bp );
check( false === strpos( $css, '1024px' ) && false !== strpos( $css, 'a-1536.jpg' ), 'Tablet necesita 2048 px y el original sólo tiene 1920: conserva el original; móvil sí cabe en 1536.' );
check( '' === $img::background_rules( '.x', 2560, array( array( 'width' => 768, 'url' => 'a-768.jpg' ) ), 'auto', $bp ), 'Sin una variante suficiente para viewport × DPR, nunca se baja de calidad.' );

// Aislamiento por sitio: el mismo archivo en otro sitio no hereda el adjunto del sitio 1.
$GLOBALS['lookups'] = 0;
$img::process( page( '<img src="https://site1.test/wp-content/uploads/2026/01/hero-768x432.jpg">' ) );
$first = $GLOBALS['lookups'];
$img::process( page( '<img src="https://site1.test/wp-content/uploads/2026/01/hero-768x432.jpg">' ) );
check( $first > 0 && $first === $GLOBALS['lookups'], 'Una URL ya resuelta no se vuelve a consultar.' );
// Sin caché de objetos persistente, el mapa por sitio sobrevive a la petición.
( new ReflectionProperty( $img, 'resolved' ) )->setValue( null, array() );
$img::save_map();
( new ReflectionProperty( $img, 'map' ) )->setValue( null, null );
$img::process( page( '<img src="https://site1.test/wp-content/uploads/2026/01/hero-768x432.jpg">' ) );
check( $first === $GLOBALS['lookups'], 'En la petición siguiente la URL sale del mapa guardado, sin consultas.' );
$GLOBALS['blog'] = 2; on();
$foreign = page( '<img src="https://site2.test/wp-content/uploads/2026/01/hero.jpg">' );
check( $foreign === $img::process( $foreign ), 'En el sitio 2 no existe el adjunto del sitio 1: la imagen queda intacta.' );
check( page( '<img src="' . $hero . '">' ) === $img::process( page( '<img src="' . $hero . '">' ) ), 'Una URL de otro sitio de la red es externa para este sitio.' );
$GLOBALS['blog'] = 1;

// Debug: cada imagen queda clasificada.
$img::process( page( '<img src="' . $hero . '"><img src="https://cdn.otro.test/a.jpg"><img src="data:image/png;base64,AA">' ) );
$report = ( new ReflectionProperty( $img, 'report' ) )->getValue();
check( array( 'RESPONSIVE', 'SIN ATTACHMENT', 'EXCLUIDA' ) === array_column( $report, 'status' ) && 10 === $report[0]['attachment_id'] && array( 1920, 1080 ) === $report[0]['original'], 'El informe clasifica cada imagen con su adjunto y dimensiones.' );
check( in_array( 'CRÍTICA', $report[0]['states'], true ), 'La primera imagen figura como crítica.' );

// Auditoría por página y estimación del ancho renderizado.
$audit = $img::audit( array(
	array( 'srcset' => 'a 1w, b 2w', 'width' => '10', 'height' => '10', 'loading' => '', 'fetchpriority' => 'high', 'file' => array( 1920, 1080 ) ),
	array( 'srcset' => '', 'width' => '300', 'height' => '', 'loading' => 'lazy', 'fetchpriority' => '', 'file' => array( 1920, 1080 ) ),
	array( 'srcset' => '', 'width' => '', 'height' => '', 'loading' => 'lazy', 'fetchpriority' => '', 'file' => array( 2560, 1440 ) ),
) );
check( array( 'total' => 3, 'srcset' => 1, 'no_srcset' => 2, 'no_dimensions' => 2, 'lazy' => 2, 'eager' => 1, 'high' => 1, 'oversized' => 2 ) === $audit, 'La auditoría cuenta cada criterio.' );
check( 390 === $img::estimate( '(max-width: 1024px) 100vw, (max-width: 1140px) 50vw, 570px', 390 ) && 570 === $img::estimate( '(max-width: 1024px) 100vw, (max-width: 1140px) 50vw, 570px', 1440 ) && 1920 === $img::estimate( '(max-width: 1920px) 100vw, 1920px', 1920 ), 'La estimación sigue el orden de las condiciones de sizes.' );

// Una imagen de la Biblioteca con width/height 1 (metadata dañada) no es un píxel de seguimiento.
$out = $img::process( page( '<img src="' . $hero . '" width="1" height="1">' ) );
check( false !== strpos( $out, 'srcset=' ), 'Imagen propia con 1×1 se sigue tratando.' );

// Ancho fijo del widget (Call to Action, Image): sizes exacto en lugar del de WordPress.
check( '75px' === $img::fixed_sizes( 'call-to-action', array( 'graphic_element' => 'image', 'graphic_image_width' => array( 'unit' => 'px', 'size' => 75 ) ), array( 'mobile' => 767, 'tablet' => 1024 ) ), 'CTA con 75 px.' );
check( '(max-width: 767px) 60px, 75px' === $img::fixed_sizes( 'call-to-action', array( 'graphic_element' => 'image', 'graphic_image_width' => array( 'unit' => 'px', 'size' => 75 ), 'graphic_image_width_mobile' => array( 'unit' => 'px', 'size' => 60 ) ), array( 'mobile' => 767, 'tablet' => 1024 ) ), 'Con ancho móvil propio.' );
check( '' === $img::fixed_sizes( 'call-to-action', array( 'graphic_element' => 'icon', 'graphic_image_width' => array( 'unit' => 'px', 'size' => 75 ) ) ) && '' === $img::fixed_sizes( 'image', array( 'width' => array( 'unit' => '%', 'size' => 50 ) ) ) && '' === $img::fixed_sizes( 'heading', array() ), 'Porcentajes, iconos y otros widgets no son deterministas.' );
check( 150 === $img::default_width( '(max-width: 150px) 100vw, 150px' ) && null === $img::default_width( '(max-width: 767px) 100vw, 50vw' ), 'sizes por defecto de WordPress reconocido.' );
on();
$cta = '<img width="150" height="150" src="https://site1.test/wp-content/uploads/2026/01/hero-300x169.jpg" srcset="a 150w, b 300w" sizes="(max-width: 300px) 100vw, 300px" data-digitalisimo-fixed="75px">';
$out = $img::process( page( $cta ) );
check( false !== strpos( $out, 'sizes="75px"' ) && false === strpos( $out, 'data-digitalisimo-fixed' ), 'El sizes de WordPress se sustituye por el ancho fijo del widget: ' . $out );
$own = '<img src="' . $hero . '" srcset="' . $hero . ' 1920w" sizes="(max-width: 767px) 100vw, 50vw" width="1920" height="1080" data-digitalisimo-fixed="75px">';
check( false !== strpos( $img::process( page( $own ) ), 'sizes="(max-width: 767px) 100vw, 50vw"' ), 'Un sizes propio (no el de WordPress) se respeta.' );

// sizes genérico: se corrige sólo con el layout real de Elementor; sin él se informa.
$GLOBALS['blog'] = 1;
on();
$generic = '<img src="' . $hero . '" srcset="' . $hero . ' 1920w" sizes="100vw" width="1920" height="1080"';
$out = $img::process( page( $generic . ' data-digitalisimo-sizes="(max-width: 767px) 100vw, 50vw">' ) );
check( false !== strpos( $out, 'sizes="(max-width: 767px) 100vw, 50vw"' ) && 1 === substr_count( $out, ' sizes=' ), 'Un sizes «100vw» se sustituye por el del layout.' );
$report = ( new ReflectionProperty( $img, 'report' ) )->getValue();
check( 'RESPONSIVE' === $report[0]['status'] && in_array( 'SIZES CORREGIDO', $report[0]['states'], true ), 'El informe indica la corrección.' );
$out = $img::process( page( $generic . '>' ) );
$report = ( new ReflectionProperty( $img, 'report' ) )->getValue();
check( false !== strpos( $out, 'sizes="100vw"' ) && in_array( 'SIZES GENÉRICO', $report[0]['states'], true ) && 'OPTIMIZADA' === $report[0]['status'], 'Sin layout conocido el sizes se conserva y se marca genérico.' );
$out = $img::process( page( $generic . ' data-digitalisimo-sizes="100vw">' ) );
$report = ( new ReflectionProperty( $img, 'report' ) )->getValue();
check( false !== strpos( $out, 'sizes="100vw"' ) && ! in_array( 'SIZES CORREGIDO', $report[0]['states'], true ), 'Si el layout también es de ancho completo, nada cambia ni se informa como corrección.' );
check( $img::generic_sizes( ' 100VW ' ) && ! $img::generic_sizes( 'auto, 100vw' ) && ! $img::generic_sizes( '(max-width: 767px) 100vw, 50vw' ), 'Sólo «100vw» a secas es genérico.' );
on( array( 'perf_img_dimensions' => 0 ) );
$img::process( page( '<img src="' . $hero . '">' ) );
$report = ( new ReflectionProperty( $img, 'report' ) )->getValue();
check( in_array( 'SIN DIMENSIONES', $report[0]['states'], true ), 'Una imagen sin width/height se marca.' );

// Clasificación decorativa: alt="" para el adjunto marcado, también con la optimización apagada.
class Digitalisimo_Integrations_Quality_Audit { public static function role( $id ) { return 10 === $id ? 'decorative' : ''; } }
( new ReflectionProperty( $img, 'has_roles' ) )->setValue( null, true );
on();
$out = $img::process( page( '<img class="wp-image-10" src="' . $hero . '" alt="Héroe">' ) );
check( false !== strpos( $out, 'alt=""' ) && false === strpos( $out, 'Héroe' ) && false !== strpos( $out, 'srcset=' ), 'Una decorativa se publica con alt="" y sigue optimizándose.' );
$out = $img::process( page( '<img class="swiper-slide-image swiper-lazy" data-src="' . $hero . '" alt="Slide">' ) );
check( false !== strpos( $out, 'alt=""' ), 'También en los slides de Swiper.' );
( new ReflectionProperty( $img, 'roles_only' ) )->setValue( null, true );
$out = $img::process( page( '<img class="wp-image-10" src="' . $hero . '" alt="Héroe">' ) );
check( page( '<img class="wp-image-10" src="' . $hero . '" alt="">' ) === $out, 'Con la optimización apagada sólo se cambia el alt.' );
$other = '<img src="https://site1.test/wp-content/uploads/otra.jpg" alt="Otra">';
check( page( $other ) === $img::process( page( $other ) ), 'Las imágenes no clasificadas quedan intactas.' );
( new ReflectionProperty( $img, 'roles_only' ) )->setValue( null, false );
( new ReflectionProperty( $img, 'has_roles' ) )->setValue( null, false );

// ALT en la salida: contexto del HTML, caption, palabra clave una sola vez y Biblioteca intacta.
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function get_post_meta( $id, $key ) { return 'digitalisimo_seo_keywords' === $key ? 'mercado digital, otra' : ( 10 === $id ? $GLOBALS['meta_alt'] : '' ); }
function get_post_field( $field, $id ) { return 10 === $id ? 'hero' : ''; }
function get_queried_object_id() { return 99; }
function is_singular() { return true; }
function get_the_title( $id ) { return 'Servicios'; }
function get_bloginfo( $key ) { return 'name' === $key ? 'DIGITALÍSIMO' : 'Agencia digital'; }
function get_theme_mod( $key ) { return 0; }
require_once __DIR__ . '/../digitalisimo-seo/includes/performance/class-image-alt.php';
$GLOBALS['meta_alt'] = '';
on( array( 'seo_alt_optimize' => 1, 'seo_alt_keyword_mode' => 'contextual', 'seo_alt_keyword' => '' ) );
( new ReflectionProperty( $img, 'alt_on' ) )->setValue( null, true );
$body = '<h2>Análisis del mercado</h2><img class="wp-image-10" src="' . $hero . '" alt="">'
	. '<figure><img src="https://site1.test/wp-content/uploads/2026/01/Equipo-Oficina.jpg"><figcaption>Equipo oficina</figcaption></figure>'
	. '<div class="elementor-widget-image-box"><img src="https://site1.test/wp-content/uploads/IMG_77.jpg" alt=""><h3 class="elementor-image-box-title">Diseño web</h3></div>'
	. '<img src="https://site1.test/wp-content/uploads/Mercado-Local.jpg"><img src="https://site1.test/wp-content/uploads/foto.jpg" alt="Foto propia">';
$out = $img::process( page( $body ) );
preg_match_all( '/<img[^>]*>/', $out, $tags );
$alts = array_map( function( $tag ) use ( $img ) { $a = $img::attributes( $tag ); return $a['alt'] ?? null; }, $tags[0] );
check( 'Análisis del mercado digital' === $alts[0], 'Título y archivo sin significado: el H2 cercano, completado con la keyword (' . $alts[0] . ').' );
check( '' === $alts[1], 'Lo generado repetiría el caption visible: alt="".' );
check( '' === $alts[2], 'Una imagen ajena a la Biblioteca con alt="" se respeta.' );
check( 'Mercado Local' === $alts[3], 'La keyword ya se usó en esta página: no se repite (' . $alts[3] . ').' );
check( 'Foto propia' === $alts[4], 'El ALT manual se conserva.' );
$report = ( new ReflectionProperty( $img, 'alt_report' ) )->getValue();
check( array( 'GENERADO', 'VACÍO CORRECTO', 'VACÍO CORRECTO', 'GENERADO', 'MANUAL' ) === array_column( $report, 'state' ), 'Estados del informe: ' . implode( ', ', array_column( $report, 'state' ) ) );
$GLOBALS['meta_alt'] = 'ALT desde Medios';
check( false !== strpos( $img::process( page( '<img class="wp-image-10" src="' . $hero . '" alt="">' ) ), 'alt="ALT desde Medios"' ), 'El ALT guardado en la Biblioteca gana a cualquier generado.' );
$GLOBALS['meta_alt'] = '';
( new ReflectionProperty( $img, 'alt_on' ) )->setValue( null, false );

echo "Imágenes: srcset, sizes, dimensiones, prioridad, exclusiones, fondos y aislamiento por sitio correctos.\n";
