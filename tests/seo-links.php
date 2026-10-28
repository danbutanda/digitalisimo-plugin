<?php
/** RM7: extracción y clasificación de enlaces y sugerencias de enlazado, sin WordPress. */
define( 'ABSPATH', __DIR__ );
function add_action() {}
require __DIR__ . '/../digitalisimo-seo/includes/class-links.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$l    = Digitalisimo_Integrations_Links::class;
$html = '<p><a href="/servicios/">a</a> <a href="https://www.sitio.test/blog/?x=1#y">b</a> <a href="//sitio.test/c">c</a> <a href="https://otro.test/x">d</a>'
	. ' <a href="#arriba">e</a> <a href="mailto:a@b.c">f</a> <a href="tel:123">g</a> <a href="javascript:void(0)">h</a> <a href="relativa">i</a> <a>j</a> <a HREF=\'https://SITIO.test/mayus\'>k</a> <a href="https://sitio.test/a?b=1&amp;c=2">l</a></p>';
$out = $l::extract( $html, 'https://sitio.test/' );
check( array( 'https://sitio.test/servicios/', 'https://www.sitio.test/blog/?x=1#y', 'https://sitio.test/c', 'https://otro.test/x', 'https://SITIO.test/mayus', 'https://sitio.test/a?b=1&c=2' ) === array_column( $out, 'url' ), 'URLs absolutas, sin anclas ni esquemas que no son páginas: ' . json_encode( array_column( $out, 'url' ) ) );
check( array( true, true, true, false, true, true ) === array_column( $out, 'internal' ), 'www y mayúsculas son el mismo sitio; otro dominio es externo.' );
check( 0 === strpos( $l::extract( '<a href="/x">x</a>', 'http://sitio.test:8080/sub/' )[0]['url'], 'http://sitio.test:8080/x' ), 'Una ruta absoluta conserva el esquema y el puerto del sitio.' );
check( array() === $l::extract( '', 'https://sitio.test/' ), 'Sin HTML no hay enlaces.' );

$text = 'Ofrecemos diseño web y SEO local en Monterrey. También un Webinar gratuito.';
$keywords = array( 10 => 'Diseño web', 11 => 'seo local', 12 => 'web', 13 => 'marketing', 14 => 'Monterrey', 15 => 'webinar' );
$found = $l::suggest( $text, array( 14 ), $keywords );
check( array( 10 => 'diseño web', 11 => 'seo local', 15 => 'webinar' ) === $found, 'Coincidencias de palabra completa, sin las ya enlazadas ni las de menos de 4 letras: ' . json_encode( $found, JSON_UNESCAPED_UNICODE ) );
check( 1 === count( $l::suggest( $text, array(), $keywords, 1 ) ), 'El límite de sugerencias se respeta.' );
check( array() === $l::suggest( 'Webinars y webs', array(), array( 1 => 'webinar' ) ), '«webinar» no coincide dentro de «webinars».' );
// Enlaces rotos: sólo «No existe» y «Error» son fallos; lo que no deja comprobarse se informa aparte.
foreach ( array( 200 => 'ok', 301 => 'ok', 404 => 'broken', 410 => 'broken', 400 => 'broken', 0 => 'error', 500 => 'error', 503 => 'error', 403 => 'blocked', 401 => 'blocked', 429 => 'blocked', 999 => 'blocked' ) as $code => $state ) check( $state === $l::classify( $code ), "Respuesta $code: $state" );
echo "Enlaces internos: extracción, clasificación, sugerencias y estados de enlaces rotos correctos.\n";
