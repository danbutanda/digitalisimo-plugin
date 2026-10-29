<?php
/** RM9: comprobaciones del análisis de contenido, sin WordPress. */
define( 'ABSPATH', __DIR__ );
function add_action() {}
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function remove_accents( $text ) { return strtr( (string) $text, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E' ) ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_title( $text ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( (string) $text ) ) ), '-' ); }
function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
require __DIR__ . '/../digitalisimo-seo/includes/class-keyword-match.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-links.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-content-analysis.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$a = Digitalisimo_Integrations_Content_Analysis::class;
$status = function( $result ) { return array_column( $result['checks'], 'status', 'id' ); };
$body = '<p>El diseño web profesional ayuda a vender.</p>' . str_repeat( '<p>' . str_repeat( 'Texto útil sobre la empresa y sus servicios. ', 10 ) . '</p>', 5 ) . '<h2>Diseño web a medida</h2><p>' . str_repeat( 'Más contenido valioso para el lector. ', 20 ) . ' diseño web.</p><img src="/x.jpg" alt="Ejemplo de diseño web"><a href="/contacto/">contacto</a>';
$good = $a::analyze( array( 'keyword' => 'Diseño web', 'title' => 'Diseño web para empresas en Monterrey', 'description' => str_repeat( 'Creamos diseño web rápido, claro y a medida para tu negocio. ', 2 ) . 'Pide tu cotización.', 'url' => 'https://s.test/diseno-web/', 'home' => 'https://s.test/', 'html' => $body ) );
$s = $status( $good );
foreach ( array( 'keyword_title', 'keyword_title_start', 'keyword_description', 'keyword_slug', 'keyword_intro', 'keyword_subheadings', 'keyword_alt', 'keyword_unique', 'content_length', 'title_length', 'description_length', 'links_internal', 'short_paragraphs', 'media' ) as $id ) check( 'pass' === ( $s[ $id ] ?? '' ), "Contenido bien hecho: $id debe pasar (" . ( $s[ $id ] ?? 'falta' ) . ')' );
check( 'info' === $s['links_external'], 'Los enlaces externos sólo informan.' );
check( $good['score'] >= 90, 'Puntuación alta: ' . $good['score'] );

$bad = $a::analyze( array( 'keyword' => 'seo local', 'title' => 'Inicio', 'description' => '', 'url' => 'https://s.test/' . str_repeat( 'pagina-larga-', 8 ) . '/', 'home' => 'https://s.test/', 'html' => '<p>' . str_repeat( 'palabra ', 160 ) . '</p>', 'duplicates' => array( 'Otra página' ) ) );
$s = $status( $bad );
check( 'fail' === $s['keyword_title'] && 'fail' === $s['content_length'] && 'fail' === $s['description_length'], 'Lo esencial que falta suspende.' );
check( 'warn' === $s['keyword_unique'] && false !== strpos( $bad['checks'][ array_search( 'keyword_unique', array_column( $bad['checks'], 'id' ), true ) ]['detail'], 'Otra página' ), 'La canibalización nombra el otro contenido.' );
check( 'warn' === $s['short_paragraphs'] && 'warn' === $s['slug_length'] && 'warn' === $s['media'] && 'warn' === $s['links_internal'], 'Párrafo largo, URL larga, sin medios ni enlaces internos.' );
check( ! isset( $s['keyword_title_start'] ) && ! isset( $s['keyword_alt'] ), 'No se evalúa lo que no aplica.' );
check( $bad['score'] < 40, 'Puntuación baja: ' . $bad['score'] );

$stuffed = $a::analyze( array( 'keyword' => 'zapatos', 'title' => 'Zapatos', 'description' => 'x', 'url' => '', 'home' => 'https://s.test/', 'html' => '<p>' . str_repeat( 'zapatos baratos ', 40 ) . '</p>' ) );
check( 'warn' === $status( $stuffed )['keyword_density'] && false !== strpos( $stuffed['checks'][ array_search( 'keyword_density', array_column( $stuffed['checks'], 'id' ), true ) ]['detail'], 'Es mucho' ), 'La sobreoptimización se avisa.' );
$none = $a::analyze( array( 'keyword' => '', 'title' => 'Título de prueba suficientemente largo', 'description' => '', 'url' => '', 'home' => 'https://s.test/', 'html' => '' ) );
check( ! array_filter( array_column( $none['checks'], 'id' ), function( $id ) { return 0 === strpos( $id, 'keyword_' ); } ), 'Sin keyword no hay comprobaciones de keyword.' );
echo "Análisis de contenido: comprobaciones, estados y puntuación correctos.\n";
