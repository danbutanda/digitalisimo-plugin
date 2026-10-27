<?php
/** RM4: las imágenes del sitemap sólo salen del propio sitio, sin repetirse y con tope por URL. */
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../digitalisimo-seo/includes/class-sitemap.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$s     = Digitalisimo_Integrations_Sitemap::class;
$hosts = array( 'sitio.test', 'cdn.sitio.test' );
$html  = '<p><img class="a" src="https://sitio.test/wp-content/uploads/a.jpg" alt=""><img src=\'//cdn.sitio.test/b.png\'>'
	. '<img data-src="x" src="https://otro.test/c.jpg"><img src="data:image/gif;base64,AAAA"><img src="/relativa.jpg">'
	. '<IMG SRC="https://SITIO.test/wp-content/uploads/a.jpg"><img src="https://sitio.test/d.jpg?x=1&amp;y=2"></p>';
$found = $s::content_images( $html, $hosts );
check( array( 'https://sitio.test/wp-content/uploads/a.jpg', 'https://cdn.sitio.test/b.png', 'https://SITIO.test/wp-content/uploads/a.jpg', 'https://sitio.test/d.jpg?x=1&y=2' ) === $found, 'Sólo imágenes absolutas de los hosts del sitio, en orden: ' . json_encode( $found ) );
check( array() === $s::content_images( '<p>sin imágenes</p>', $hosts ), 'Un contenido sin imágenes no publica ninguna.' );
check( 2 === count( $s::content_images( str_repeat( '<img src="https://sitio.test/x.jpg"><img src="https://sitio.test/y.jpg"><img src="https://sitio.test/z.jpg">', 3 ), $hosts, 2 ) ), 'El tope por URL se respeta.' );
function absint( $v ) { return abs( (int) $v ); }
check( 'es' === $s::news_language( 'es_MX' ) && 'en' === $s::news_language( 'en_US' ) && 'zh-cn' === $s::news_language( 'zh_CN' ) && 'zh-tw' === $s::news_language( 'zh_TW' ), 'Idioma de Google News.' );
check( '3,7,12' === $s::sanitize_ids( '3, 7;x 12 3 0 -1' ) && '' === $s::sanitize_ids( array( 1 ) ), 'Las categorías excluidas son una lista de números.' );
echo "Sitemap: imágenes (hosts propios, orden y tope) e idioma y exclusiones de noticias correctos.\n";
