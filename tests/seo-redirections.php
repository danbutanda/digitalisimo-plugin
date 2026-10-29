<?php
/** RM2, RM3 y RM5: coincidencias de redirecciones, registro de 404 y códigos de verificación, sin base de datos. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array();
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? (string) $url : ''; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function add_action() {}
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key, $default = null ) { return $GLOBALS['options'][ $key ] ?? $default; } }
require __DIR__ . '/../digitalisimo-seo/includes/class-redirections.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-bulk.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-404-monitor.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-verification.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$r = Digitalisimo_Integrations_Redirections::class;

// Rutas relativas a la portada del sitio, también en subdirectorios.
check( array( 'vieja/pagina', '' ) === $r::relative( '/vieja/pagina/' ), 'Una ruta pierde sus barras de los extremos.' );
check( array( 'vieja', 'a=1' ) === $r::relative( 'https://otro.test/vieja/?a=1#x' ), 'Una URL completa se reduce a su ruta y su query.' );
check( array( 'pagina', '' ) === $r::relative( '/sub/pagina/', '/sub/' ), 'La ruta del sitio en un subdirectorio se descuenta.' );
check( array( 'subasta', '' ) === $r::relative( '/subasta/', '/sub/' ), 'Un prefijo parecido no es el subdirectorio del sitio.' );
check( array( 'café', '' ) === $r::relative( '/caf%C3%A9/' ), 'La ruta se compara decodificada.' );

// Orígenes y destinos: lo inválido nunca se guarda.
check( null === $r::sanitize_source( array( 'pattern' => '/', 'comparison' => 'exact' ) ), 'La portada nunca es un origen.' );
check( null === $r::sanitize_source( array( 'pattern' => '(abc', 'comparison' => 'regex' ) ), 'Una expresión regular rota se rechaza.' );
check( null === $r::sanitize_source( array( 'pattern' => 'x', 'comparison' => 'like' ) ), 'Una comparación desconocida se rechaza.' );
check( array( 'pattern' => 'vieja', 'comparison' => 'exact', 'ignore' => 'case' ) === $r::sanitize_source( array( 'pattern' => 'https://sitio.test/vieja/', 'comparison' => 'exact', 'ignore' => 'case' ) ), 'Un origen exacto se normaliza a su ruta.' );
check( '' === $r::sanitize_target( 'javascript:alert(1)' ), 'Un destino con otro esquema se rechaza.' );
check( '' === $r::sanitize_target( '//malo.test/x' ), 'Un destino relativo al protocolo se rechaza.' );
check( '/nueva/' === $r::sanitize_target( 'nueva/' ), 'Una ruta del sitio se guarda con barra inicial.' );
check( 'https://otro.test/a' === $r::sanitize_target( 'https://otro.test/a' ), 'Una URL http(s) se conserva.' );

// Índice: exactas por clave, patrones en orden de recencia.
$rows = array(
	array( 'id' => 9, 'sources' => json_encode( array( array( 'pattern' => '^blog/(\d+)/(.*)$', 'comparison' => 'regex', 'ignore' => '' ) ) ), 'url_to' => '/articulos/$2', 'header_code' => 301 ),
	array( 'id' => 8, 'sources' => json_encode( array( array( 'pattern' => 'tienda', 'comparison' => 'start', 'ignore' => '' ) ) ), 'url_to' => '/productos/', 'header_code' => 302 ),
	array( 'id' => 7, 'sources' => json_encode( array( array( 'pattern' => 'Vieja', 'comparison' => 'exact', 'ignore' => 'case' ), array( 'pattern' => 'antigua', 'comparison' => 'exact', 'ignore' => '' ) ) ), 'url_to' => '/nueva/', 'header_code' => 301 ),
	array( 'id' => 6, 'sources' => json_encode( array( array( 'pattern' => '.pdf', 'comparison' => 'end', 'ignore' => '' ) ) ), 'url_to' => '', 'header_code' => 410 ),
	array( 'id' => 5, 'sources' => json_encode( array( array( 'pattern' => 'promo', 'comparison' => 'contains', 'ignore' => 'case' ) ) ), 'url_to' => '/ofertas/', 'header_code' => 307 ),
	array( 'id' => 4, 'sources' => json_encode( array( array( 'pattern' => 'solo?utm=1', 'comparison' => 'exact', 'ignore' => '' ) ) ), 'url_to' => '/con-query/', 'header_code' => 301 ),
	array( 'id' => 3, 'sources' => 'no es json', 'url_to' => '/x/', 'header_code' => 301 ),
);
$index = $r::build_index( $rows );
check( array( 9, 8, 6, 5 ) === $index['scan'], 'Sólo las reglas con patrones se recorren, de la más reciente a la más antigua.' );
check( ! isset( $index['rules'][3] ), 'Una fila con orígenes ilegibles no entra en el índice.' );
check( 7 === $index['exact']['antigua'] && 7 === $index['exact_ci']['vieja'], 'Los orígenes exactos se indexan por clave.' );
$find = function( $path, $query = '' ) use ( $r, $index ) { $m = $r::find( $index, $path, $query ); return $m ? array( $m['rule']['i'], $r::destination( $m ) ) : null; };
check( array( 7, '/nueva/' ) === $find( 'VIEJA' ), 'Exacta sin distinguir mayúsculas.' );
check( null === $find( 'Antigua' ), 'Exacta que distingue mayúsculas.' );
check( array( 9, '/articulos/hola' ) === $find( 'blog/2024/hola' ), 'Regex con referencias en el destino.' );
check( array( 8, '/productos/' ) === $find( 'tienda/zapatos' ), 'Empieza por.' );
check( array( 6, '' ) === $find( 'docs/manual.pdf' ), 'Termina en (410 sin destino).' );
check( array( 5, '/ofertas/' ) === $find( 'verano/PROMOCION' ), 'Contiene, sin distinguir mayúsculas.' );
check( array( 4, '/con-query/' ) === $find( 'solo', 'utm=1' ), 'Si la ruta sola no coincide se prueba con la query.' );
check( null === $find( 'nada/que/ver' ), 'Sin coincidencia no hay redirección.' );

// 404: qué se registra.
$m = Digitalisimo_Integrations_404_Monitor::class;
check( '/falta' === $m::loggable( '/falta?utm=1', '', true ), 'Ignorar la query registra la ruta sola.' );
check( '/falta?utm=1' === $m::loggable( '/falta?utm=1', '', false ), 'Sin ignorar la query se registra completa.' );
check( '' === $m::loggable( '/app.js.map', ".map\n/cache/", true ), 'Una exclusión no se registra.' );
check( '' === $m::loggable( '/wp-admin/x', '', true ), 'El escritorio nunca se registra.' );
check( 50 === $m::sanitize_limit( 3 ) && 20000 === $m::sanitize_limit( 999999 ), 'El límite queda acotado.' );
check( "/.map\n.tmp" === $m::sanitize_exclusions( "/.map\n\n.tmp\n/.map" ), 'Las exclusiones se limpian sin repetirse.' );

// Verificación: sólo códigos, nunca HTML.
$v = Digitalisimo_Integrations_Verification::class;
check( 'abc-123_XYZ' === $v::sanitize_code( '<meta name="google-site-verification" content="abc-123_XYZ" />' ), 'De una etiqueta pegada sólo queda el código.' );
check( '' === $v::sanitize_code( '"><script>alert(1)</script>' ), 'Un valor con HTML se descarta.' );
check( "facebook-domain-verification|abc\np:domain_verify|zz" === $v::sanitize_custom( "Facebook-Domain-Verification|abc\nsin separador\n<b>|x\np:domain_verify|zz" ), 'Las etiquetas propias sólo admiten nombre|código válidos.' );
$GLOBALS['options'] = array( 'seo_verify_google' => 'g1', 'seo_verify_bing' => '<meta name="msvalidate.01" content="B2">', 'seo_verify_custom' => "google-site-verification|otro\nyandex-verification|y3" );
check( array( 'google-site-verification' => 'g1', 'msvalidate.01' => 'B2', 'yandex-verification' => 'y3' ) === $v::tags(), 'Los campos propios ganan a una etiqueta repetida en «Otras etiquetas».' );

// RM2, segunda parte: programación, categorías y CSV.
$now  = strtotime( '2026-06-01 12:00:00 UTC' );
$rule = function( $id, $pattern, $starts = null, $ends = null ) { return array( 'id' => $id, 'sources' => json_encode( array( array( 'pattern' => $pattern, 'comparison' => 'exact', 'ignore' => '' ) ) ), 'url_to' => '/x/', 'header_code' => 301, 'starts' => $starts, 'ends' => $ends ); };
$index = $r::build_index( array( $rule( 1, 'siempre' ), $rule( 2, 'futura', '2026-06-02 00:00:00' ), $rule( 3, 'vencida', null, '2026-05-01 00:00:00' ), $rule( 4, 'vigente', '2026-05-01 00:00:00', '2026-06-10 00:00:00' ) ), $now );
check( array( 'siempre', 'vigente' ) === array_keys( $index['exact'] ), 'Sólo entran las reglas sin programación o dentro de su periodo.' );
check( strtotime( '2026-06-02 00:00:00 UTC' ) === $index['next'], 'El índice anota el próximo cambio: ' . $index['next'] );
check( 0 === $r::build_index( array( $rule( 1, 'a' ) ), $now )['next'], 'Sin programación no hay próximo cambio.' );
check( '2026-06-02 08:05:00' === $r::sanitize_datetime( '2026-06-02T08:05' ) && '2026-06-02 00:00:00' === $r::sanitize_datetime( '2026-06-02' ) && null === $r::sanitize_datetime( '2026-02-30 10:00' ) && null === $r::sanitize_datetime( 'mañana' ) && null === $r::sanitize_datetime( '2026-06-02 25:00' ), 'Las fechas se validan.' );
check( 0 === $r::timestamp( null ) && 0 === $r::timestamp( '0000-00-00 00:00:00' ), 'Una fecha vacía no programa nada.' );
check( 100 === strlen( $r::sanitize_category( str_repeat( 'a', 300 ) ) ) && 'Campaña 2026' === $r::sanitize_category( " <b>Campaña</b>\n 2026 " ), 'La categoría se limpia y se acota.' );
$rows = $r::csv_rows( array( 'id' => 7, 'sources' => array( array( 'pattern' => 'a', 'comparison' => 'exact', 'ignore' => '' ), array( 'pattern' => '^b/(.*)$', 'comparison' => 'regex', 'ignore' => 'case' ) ), 'url_to' => '=HYPERLINK("x")', 'header_code' => 301, 'category' => 'Blog', 'status' => 'active', 'starts' => '2026-06-02 00:00:00', 'ends' => null ) );
check( 2 === count( $rows ) && array( '7', '/a', 'exact', "'=HYPERLINK(\"x\")", '301', 'Blog', 'active', '', '2026-06-02 00:00:00', '' ) === $rows[0] && '^b/(.*)$' === $rows[1][1] && 'case' === $rows[1][7], 'Una fila por origen, con el destino protegido contra fórmulas.' );
$csv = array();
foreach ( $rows as $cells ) $csv[] = array_combine( $r::CSV_COLUMNS, array_map( 'strval', $cells ) );
$csv[] = array( 'id' => '', 'source' => '', 'destination' => '/nada/' );
$csv[] = array( 'id' => '', 'source' => '/rm', 'matching' => 'contains', 'destination' => '/y/', 'type' => '302', 'category' => 'Uno, Dos', 'status' => 'inactive' );
$rules = $r::csv_rules( $csv );
check( 2 === count( $rules ) && 2 === count( $rules[0]['sources'] ) && '=HYPERLINK("x")' === $rules[0]['url_to'] && 'Blog' === $rules[0]['category'] && '2026-06-02 00:00:00' === $rules[0]['starts'] && 'csv' === $rules[0]['origin'], 'Las filas con el mismo id vuelven a ser una regla, sin la protección de fórmulas.' );
check( array( 'pattern' => '/rm', 'comparison' => 'contains', 'ignore' => '' ) === $rules[1]['sources'][0] && 302 === $rules[1]['header_code'] && 'inactive' === $rules[1]['status'] && 'Uno' === $rules[1]['category'], 'Una fila de Rank Math: varias categorías se quedan en la primera.' );

echo "Redirecciones, 404 y verificación: coincidencias, saneado y exclusiones correctos.\n";
