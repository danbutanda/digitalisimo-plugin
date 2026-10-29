<?php
/** RM14: saneado de la edición rápida, masiva y CSV, y filtros del listado, sin WordPress. */
define( 'ABSPATH', __DIR__ );
function add_action() {}
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_textarea_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? (string) $url : ''; }
function remove_accents( $text ) { return strtr( (string) $text, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u' ) ); }
function add_filter() {}
require __DIR__ . '/../digitalisimo-seo/includes/class-settings.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-keyword-match.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-content-analysis.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-bulk.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$b = Digitalisimo_Integrations_SEO_Bulk::class;
check( '1' === $b::flag( 'Sí' ) && '1' === $b::flag( 'yes' ) && '' === $b::flag( '0' ) && '' === $b::flag( 'NO' ) && null === $b::flag( '' ) && null === $b::flag( 'quizá' ), 'Sí/no admite 1/0, sí/no y yes/no; lo demás no cambia nada.' );
check( "'=HYPERLINK(1)" === $b::csv_cell( '=HYPERLINK(1)' ) && "'+1" === $b::csv_cell( '+1' ) && 'Normal' === $b::csv_cell( 'Normal' ) && '' === $b::csv_cell( '' ), 'Una fórmula se exporta como texto.' );
check( '=HYPERLINK(1)' === $b::csv_value( "'=HYPERLINK(1)" ) && "'Comilla" === $b::csv_value( "'Comilla" ), 'Al importar se quita sólo la comilla que protegía una fórmula.' );
$changes = $b::changes( array( 'id' => '5', 'seo_title' => '<b>Título</b>', 'seo_description' => '', 'keywords' => 'Diseño web, diseño web, SEO, a, b, c, d', 'canonical' => 'javascript:alert(1)', 'noindex' => 'sí', 'nofollow' => '', 'social_title' => "'=SUMA(A1)", 'breadcrumb_title' => '  ' ), 3 );
check( array( 'title' => 'Título', 'social_title' => '=SUMA(A1)', 'keywords' => 'Diseño web, SEO, a', 'noindex' => '1' ) === $changes, 'Celdas vacías se conservan, keywords sin duplicados y con el máximo, canonical inválido descartado: ' . json_encode( $changes, JSON_UNESCAPED_UNICODE ) );
check( array( 'canonical' => 'https://s.test/x' ) === $b::changes( array( 'canonical' => ' https://s.test/x ' ) ), 'Un canonical válido se guarda.' );
check( array() === $b::filter_meta( 'otro' ), 'Un filtro desconocido no filtra.' );
check( 'NOT EXISTS' === $b::filter_meta( 'no_description' )[0]['compare'] && 'digitalisimo_seo_noindex' === $b::filter_meta( 'noindex' )[0]['key'] && '<' === $b::filter_meta( 'low_score' )[0]['compare'], 'Cada filtro consulta su meta.' );
$vars = $b::changes( array( 'seo_title' => '%title% %sep% %date(Y)%', 'seo_description' => '%description% · %category%' ) );
check( '%title% %sep% %date(Y)%' === $vars['title'] && '%description% · %category%' === $vars['description'], 'Las variables %date%, %description% y %category% sobreviven a la edición rápida y al CSV: ' . json_encode( $vars ) );
echo "Edición rápida, masiva y CSV: saneado y filtros correctos.\n";
