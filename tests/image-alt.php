<?php
/**
 * ALT en la salida: nunca sobrescribe un ALT manual, respeta decorativas y
 * captions, genera con contexto y no repite la palabra clave en todas las imágenes.
 */
define( 'ABSPATH', __DIR__ );
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-image-alt.php';
$a = Digitalisimo_Integrations_Image_Alt::class;
function check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function page( $mode = 'contextual', $keyword = 'mercado digital' ) { return Digitalisimo_Integrations_Image_Alt::page( 'DIGITALÍSIMO', 'Agencia de marketing digital en México', $keyword, $mode, 'Servicios de marketing' ); }

// Limpieza de nombres.
check( 'Daniel Butanda' === $a::clean( 'Daniel-Butanda.webp' )['text'] && $a::clean( 'Daniel-Butanda.webp' )['person'], 'Nombre y apellido se reconocen como persona.' );
check( 'Gráfico mercado' === $a::clean( 'https://x.test/wp-content/uploads/2026/01/Grafico-Mercado-1024x576.webp' )['text'], 'Sin tamaño ni extensión, con tilde recuperada.' );
check( 'Kimball' === $a::clean( 'Logo-Kimball.webp' )['text'] && $a::clean( 'Logo-Kimball.webp' )['logo'], 'La palabra logo se retira y se marca.' );
foreach ( array( 'IMG_2034.jpg', 'WhatsApp-Image-2024-05-01-at-10.32.11.jpeg', 'a8f3c9e1b2d4.png', 'hero-scaled.jpg', 'DSC0001.JPG' ) as $junk ) check( '' === $a::clean( $junk )['text'], 'Nombre sin significado: ' . $junk );
check( 'SEO local México' === $a::clean( 'SEO-local-Mexico-1.webp' )['text'] && 'Marketing digital' === $a::clean( 'MARKETING-DIGITAL.png' )['text'], 'Siglas cortas se conservan; mayúsculas largas se normalizan.' );
check( ! $a::clean( 'Grafico-Mercado.webp' )['person'] && ! $a::clean( 'equipo-oficina.jpg' )['person'] && ! $a::clean( 'Algo-Bonito.jpg' )['person'], 'Objetos no se toman por personas.' );
check( $a::clean( 'Maria-Lopez-Garcia.jpg' )['person'], 'Nombre con dos apellidos.' );

// Ejemplos del objetivo, modo contextual.
$p = page();
check( 'Daniel Butanda de DIGITALÍSIMO' === $a::generate( array( 'file' => 'Daniel-Butanda.webp' ), $p )['alt'], 'Persona + nombre del sitio.' );
check( 'Gráfico mercado digital' === $a::generate( array( 'file' => 'Grafico-Mercado.webp' ), $p )['alt'] && 1 === $p['keyword_used'], 'La palabra clave completa el ALT sin repetir palabras.' );
check( 'Kimball' === $a::generate( array( 'file' => 'Logo-Kimball.webp' ), $p )['alt'], 'Un logo de cliente lleva sólo la marca.' );
check( 'DIGITALÍSIMO - Agencia de marketing digital en México' === $a::generate( array( 'main_logo' => true, 'file' => 'logo.png' ), $p )['alt'], 'Logo principal: nombre y descripción del sitio.' );
check( 'Mercado regional' === $a::generate( array( 'file' => 'mercado-regional.jpg' ), $p )['alt'], 'La palabra clave se añade como mucho una vez por página.' );
$p = page();
check( 'Equipo oficina' === $a::generate( array( 'file' => 'equipo-oficina.jpg' ), $p )['alt'] && 0 === $p['keyword_used'], 'Sin relación con la imagen no se añade la palabra clave.' );
$p = page(); $p['heading'] = 'Estrategia para el mercado digital';
check( 'Equipo oficina – mercado digital' === $a::generate( array( 'file' => 'equipo-oficina.jpg' ), $p )['alt'], 'Relevante por el encabezado cercano: se añade como complemento.' );

// Modos.
$p = page( 'off' );
check( 'Gráfico mercado' === $a::generate( array( 'file' => 'Grafico-Mercado.webp' ), $p )['alt'] && 'Daniel Butanda' === $a::generate( array( 'file' => 'Daniel-Butanda.webp' ), $p )['alt'], 'Desactivado: sin palabra clave ni marca.' );
$p = page( 'fallback' );
check( 'Gráfico mercado' === $a::generate( array( 'file' => 'Grafico-Mercado.webp' ), $p )['alt'], 'Sólo fallback: con contexto no se añade.' );
$p = Digitalisimo_Integrations_Image_Alt::page( 'Sitio', '', 'mercado digital', 'fallback', '' );
check( 'mercado digital' === $a::generate( array( 'file' => 'IMG_1.jpg' ), $p )['alt'] && '' === $a::generate( array( 'file' => 'IMG_2.jpg' ), $p )['alt'], 'Sólo fallback: sin ningún contexto se usa una vez, nunca en todas.' );
$p = Digitalisimo_Integrations_Image_Alt::page( 'Sitio', '', 'mercado digital', 'off', '' );
check( '' === $a::generate( array( 'file' => 'IMG_1.jpg' ), $p )['alt'], 'Desactivado: sin contexto no se inventa nada.' );

// Prioridad de fuentes y repetición.
$p = page();
$r = $a::generate( array( 'title' => 'Fachada de la oficina', 'file' => 'IMG_1.jpg', 'widget_title' => 'Visítanos' ), $p );
check( 'Fachada de la oficina' === $r['alt'] && 'Título del adjunto' === $r['source'], 'El título útil del adjunto va primero.' );
$r = $a::generate( array( 'title' => 'IMG_1', 'file' => 'IMG_1.jpg', 'widget_title' => 'Consultoría SEO' ), $p );
check( 'Consultoría SEO' === $r['alt'] && 'Título del widget' === $r['source'], 'Título y archivo sin significado: título del widget.' );
$p['heading'] = 'Nuestro equipo';
check( 'Nuestro equipo' === $a::generate( array( 'file' => 'IMG_1.jpg' ), $p )['alt'], 'Luego el H2/H3 cercano.' );
$q = Digitalisimo_Integrations_Image_Alt::page( 'S', '', '', 'off', 'Servicios de marketing' );
check( 'Servicios de marketing' === $a::generate( array( 'file' => 'IMG_1.jpg' ), $q )['alt'], 'Por último, el contenido de la página.' );

$p = page( 'off' );
$first  = $a::apply( array(), array( 'attachment' => 1, 'title' => 'Oficina central', 'file' => 'Recepcion.jpg' ), $p );
$second = $a::apply( array(), array( 'attachment' => 2, 'title' => 'Oficina central', 'file' => 'Sala-juntas.jpg' ), $p );
check( 'Oficina central' === $first['alt'] && 'Sala juntas' === $second['alt'] && 'GENERADO' === $second['state'], 'Si una fuente ya se usó en la página, se pasa a la siguiente para no repetir.' );

// apply(): manual, decorativa, caption, repetidos y genéricos.
$p = page();
$r = $a::apply( array( 'alt' => 'Equipo en reunión' ), array( 'attachment' => 5 ), $p );
check( null === $r['alt'] && 'MANUAL' === $r['state'], 'Un ALT manual no se toca.' );
$r = $a::apply( array( 'alt' => 'Equipo en reunión' ), array( 'attachment' => 6 ), $p );
check( null === $r['alt'] && 'REPETIDO' === $r['state'], 'Un ALT manual repetido se informa sin cambiarlo.' );
$r = $a::apply( array( 'alt' => '' ), array( 'attachment' => 7, 'meta_alt' => 'Desde Medios', 'file' => 'x.jpg' ), $p );
check( 'Desde Medios' === $r['alt'] && 'MANUAL' === $r['state'], 'El ALT de la Biblioteca tiene prioridad sobre cualquier generado.' );
$r = $a::apply( array( 'alt' => 'Nuestro equipo' ), array( 'attachment' => 8, 'caption' => 'Nuestro equipo.' ), $p );
check( '' === $r['alt'] && 'IGUAL A CAPTION' === $r['state'], 'ALT igual al caption: alt="" sólo en la página.' );
$r = $a::apply( array( 'alt' => 'Algo' ), array( 'attachment' => 9, 'role' => 'decorative' ), $p );
check( '' === $r['alt'] && 'DECORATIVO' === $r['state'], 'Decorativa: alt="".' );
$r = $a::apply( array( 'alt' => 'Imagen' ), array( 'attachment' => 10 ), $p );
check( null === $r['alt'] && 'POSIBLEMENTE GENÉRICO' === $r['state'] && 'POSIBLEMENTE GENÉRICO' === $a::apply( array( 'alt' => 'DIGITALÍSIMO' ), array(), $p )['state'], 'ALT genérico o igual al nombre del sitio se informa.' );
$r = $a::apply( array( 'alt' => '' ), array( 'attachment' => 11, 'file' => 'Daniel-Butanda.webp' ), $p );
check( 'Daniel Butanda de DIGITALÍSIMO' === $r['alt'] && 'GENERADO' === $r['state'] && 'Nombre del archivo' === $r['source'], 'Sin ALT se genera.' );
$r = $a::apply( array(), array( 'attachment' => 12, 'file' => 'Daniel-Butanda.webp' ), $p );
check( 'REPETIDO' === $r['state'], 'Un generado igual a otro de la página se marca repetido.' );
$r = $a::apply( array( 'alt' => '' ), array( 'attachment' => 13, 'file' => 'Equipo-Oficina.jpg', 'caption' => 'Equipo oficina' ), $p );
check( null === $r['alt'] && 'VACÍO CORRECTO' === $r['state'], 'Si lo generado repetiría el caption, alt="" es lo correcto.' );
$r = $a::apply( array( 'alt' => '' ), array( 'attachment' => 0, 'file' => 'Algo-Bonito.jpg' ), $p );
check( null === $r['alt'] && 'VACÍO CORRECTO' === $r['state'], 'alt="" en una imagen ajena a la Biblioteca se respeta.' );
$r = $a::apply( array(), array( 'attachment' => 0, 'file' => 'Algo-Bonito.jpg' ), $p );
check( 'Algo bonito' === $r['alt'], 'Sin atributo alt también se genera en imágenes ajenas.' );
$r = $a::apply( array(), array( 'skip_empty' => true, 'file' => 'pixel.gif' ), $p );
check( '' === $r['alt'] && 'VACÍO CORRECTO' === $r['state'], 'Iconos y píxeles reciben alt="".' );
$p = Digitalisimo_Integrations_Image_Alt::page( 'S', '', '', 'off', '' );
$r = $a::apply( array(), array( 'attachment' => 3, 'file' => 'IMG_1.jpg' ), $p );
check( null === $r['alt'] && 'SIN ALT' === $r['state'], 'Sin ningún contexto no se inventa un ALT.' );

// Ninguna página repite la palabra clave en todas sus imágenes.
$p = page();
$alts = array();
foreach ( array( 'Mercado-local.jpg', 'Mercado-global.jpg', 'Mercado-nuevo.jpg', 'Mercado-regional.jpg' ) as $i => $file ) $alts[] = $a::apply( array(), array( 'attachment' => 20 + $i, 'file' => $file ), $p )['alt'];
check( 1 === count( array_filter( $alts, function( $alt ) { return false !== strpos( $alt, 'digital' ); } ) ), 'La palabra clave aparece en una sola imagen: ' . implode( ' / ', $alts ) );

// Contexto en el HTML.
$html = '<figure class="wp-caption"><img src="a.jpg" alt="x"><figcaption class="wp-caption-text">Texto <b>visible</b></figcaption></figure>';
$offset = strpos( $html, '<img' );
check( 'Texto visible' === $a::caption( $html, $offset, strlen( '<img src="a.jpg" alt="x">' ) ), 'El figcaption de la misma figura se lee.' );
check( '' === $a::caption( '<figure></figure><img src="a.jpg"><figcaption>Otro</figcaption>', 17, 17 ), 'Un figcaption fuera de su figura no cuenta.' );
$box = '<div class="elementor-widget-image-box"><img src="a.jpg"><div class="elementor-image-box-content"><h3 class="elementor-image-box-title">Diseño web</h3></div></div>';
check( 'Diseño web' === $a::widget_title( $box, strpos( $box, '<img' ), 16 ), 'Título del Image Box.' );
$logo = '<div class="elementor-element elementor-widget elementor-widget-theme-site-logo"><div class="elementor-widget-container"><a href="/"><img src="l.png"></a>';
check( $a::main_logo( $logo, strpos( $logo, '<img' ), array(), 0, 0 ) && $a::main_logo( '<img class="custom-logo">', 0, array( 'class' => 'custom-logo' ), 0, 0 ) && $a::main_logo( '', 0, array(), 44, 44 ) && ! $a::main_logo( '<div class="elementor-widget-image"><img>', 34, array(), 0, 0 ), 'Logo principal por widget, clase o custom_logo.' );

// Ajustes.
check( 'contextual' === $a::sanitize_mode( 'otro' ) && 'fallback' === $a::sanitize_mode( 'fallback' ), 'Modo desconocido vuelve a Contextual.' );
check( 'Agencia SEO' === $a::legacy_keyword( '%title% | Agencia SEO' ) && '' === $a::legacy_keyword( '%keyword%' ), 'De la plantilla antigua sólo se conserva el texto fijo.' );
check( $a::relevant( 'marketing digital', 'Estrategias de marketing' ) && ! $a::relevant( 'marketing digital', 'Equipo oficina' ), 'Relevancia por vocabulario compartido.' );

echo "ALT: manual intacto, decorativas, captions, generación contextual y palabra clave sin repetir correctos.\n";
