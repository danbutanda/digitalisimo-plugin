<?php
/**
 * Schema de la organización o del negocio local sin datos inventados (auditoría SEO del 2026-11-24, punto A2), y avisos
 * de las plantillas de título (A3), sin WordPress:
 *   1. sin nada configurado no hay horario ni logo (nada de 24×7 ni logo de demo por omisión);
 *   2. el nombre (WebSite, organización, negocio) sale sin emoji;
 *   3. lo que falta (dirección, horario, teléfono) no se publica y se avisa; un horario 24 horas los 7 días elegido a
 *      propósito se publica (puede ser real) y sólo se informa en el MCP;
 *   4. sameAs lleva las redes y el Perfil de Google Business configurados; sin perfiles, o sin Google Business con SEO
 *      local, se avisa;
 *   5. el texto fijo de una plantilla de títulos («%title% 🤖 …») se avisa sin cambiar la plantilla.
 * Datos inventados.
 */
require_once __DIR__ . '/fixtures/php74.php';
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['settings'] = array();
$GLOBALS['options']  = array();
$GLOBALS['blogname'] = 'Plomería Ejemplo 🥇';

class Digitalisimo_Integrations_Settings { public static function get( $key, $fallback = '' ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; } }
class Digitalisimo_Integrations_SEO_Resolver {
	public static function option( $key, $default = null ) { return Digitalisimo_Integrations_Settings::get( $key, $default ); }
	public static function inherits_network( $key ) { return false; }
	public static function foreign_seo() { return ''; }
}
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function get_theme_mod( $key, $default = false ) { return $default; }
function get_bloginfo( $key = '' ) { return 'name' === $key ? $GLOBALS['blogname'] : ( 'language' === $key ? 'es-MX' : '' ); }
function home_url( $path = '' ) { return 'https://plomeria-ejemplo.test' . $path; }
function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function esc_url_raw( $url ) { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : ''; }
function is_email( $v ) { return (bool) filter_var( $v, FILTER_VALIDATE_EMAIL ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }

require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-vocabulary.php';
require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-rules.php';
require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-graph.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-local-business.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-social-profiles.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-brand-check.php';
$G = 'Digitalisimo_Integrations_Schema_Graph';
$B = 'Digitalisimo_Integrations_Brand_Check';

$fails = 0;
function check( $ok, $message ) { global $fails; if ( $ok ) return; $fails++; fwrite( STDERR, "FALLA: {$message}\n" ); }
function graph() {
	$page = array( 'kind' => 'home', 'url' => 'https://plomeria-ejemplo.test/', 'title' => 'Inicio', 'description' => '', 'breadcrumbs' => array() );
	return Digitalisimo_Integrations_Schema_Graph::build( Digitalisimo_Integrations_Schema_Graph::site(), $page );
}
function node( $graph, $type ) { foreach ( $graph as $n ) if ( in_array( $type, (array) $n['@type'], true ) ) return $n; return null; }
function codes() { return array_column( Digitalisimo_Integrations_Brand_Check::warnings(), 'code' ); }
function message( $code ) { foreach ( Digitalisimo_Integrations_Brand_Check::warnings() as $w ) if ( $code === $w['code'] ) return $w['message']; return ''; }

// 1. Sin nada configurado: ni horario ni logo.
$graph = graph();
$json  = json_encode( $graph, JSON_UNESCAPED_UNICODE );
check( false === strpos( $json, 'openingHours' ) && false === strpos( $json, '"logo"' ) && false === strpos( $json, '23:59' ), 'Sin datos no se publica horario ni logo: ' . $json );

// 2. Nombres sin emoji.
check( 'Plomería Ejemplo' === $B::plain_name( 'Plomería Ejemplo 🥇' ) && 'Marca ®' === $B::plain_name( 'Marca ® 🤖' ), 'plain_name quita emojis y conserva ®.' );
check( '🥇' === $B::plain_name( '🥇' ), 'Un nombre que sólo es emoji no queda vacío.' );
check( "Marca \xE9 Uno" === $B::plain_name( "Marca \xE9 Uno" ), 'Un nombre con UTF-8 inválido se queda como estaba, no vacío.' );
check( 'Plomería Ejemplo' === node( $graph, 'WebSite' )['name'] && 'Plomería Ejemplo' === node( $graph, 'Organization' )['name'], 'WebSite y la organización salen sin emoji: ' . $json );
check( in_array( 'name_emoji', codes(), true ) && false !== strpos( message( 'name_emoji' ), '%sitename%' ), 'Se avisa del nombre con emoji y de que en los títulos sí sale.' );
$GLOBALS['settings']['seo_organization_name'] = 'Plomería Ejemplo S.A. 🔧';
$graph = graph();
check( 'Plomería Ejemplo S.A.' === node( $graph, 'Organization' )['name'] && false === strpos( json_encode( $graph, JSON_UNESCAPED_UNICODE ), '🥇' ), 'Nombre de la organización y alternateName sin emoji.' );

// 3. SEO local: lo que falta no se publica y se avisa.
$GLOBALS['settings'] += array( 'seo_local_enabled' => 1, 'seo_local_type' => 'Plumber', 'seo_local_city' => 'Ciudad Ejemplo', 'seo_local_address' => 'Calle Falsa 123', 'seo_local_country' => 'MX', 'seo_local_phone_cc' => '+52', 'seo_local_phone' => '8710000000' );
$graph = graph();
$biz   = node( $graph, 'Plumber' );
check( $biz && '+52 8710000000' === $biz['telephone'] && ! isset( $biz['openingHoursSpecification'] ), 'Sin horario elegido no se publica horario: ' . json_encode( $biz, JSON_UNESCAPED_UNICODE ) );
check( in_array( 'local_missing', codes(), true ) && false !== strpos( message( 'local_missing' ), 'el horario' ) && false === strpos( message( 'local_missing' ), 'teléfono' ), 'Se avisa que falta el horario (y sólo eso): ' . message( 'local_missing' ) );
$GLOBALS['settings']['seo_local_phone'] = '';
$GLOBALS['settings']['seo_local_address'] = '';
$graph = graph();
check( ! node( $graph, 'Plumber' ) && ! isset( node( $graph, 'Organization' )['telephone'] ) && ! isset( node( $graph, 'Organization' )['address'] ), 'Sin dirección ni teléfono no se publica el negocio, ni teléfono ni dirección vacíos.' );
check( false !== strpos( message( 'local_missing' ), 'la dirección' ) && false !== strpos( message( 'local_missing' ), 'el teléfono' ), 'El aviso dice qué falta: ' . message( 'local_missing' ) );
$GLOBALS['settings']['seo_local_phone'] = '8710000000';
$GLOBALS['settings']['seo_local_address'] = 'Calle Falsa 123';

// Horario 24×7: se publica lo elegido, pero se avisa para confirmarlo.
$always = array(); foreach ( array_keys( Digitalisimo_Integrations_Local_Business::days() ) as $day ) $always[ $day ] = array( 'state' => 'always' );
$GLOBALS['settings']['seo_local_schedule'] = $always;
check( in_array( 'local_24x7', codes(), true ) && ! in_array( 'local_missing', codes(), true ), 'Abierto 24 horas los 7 días se informa (en el MCP).' );
$hours = node( graph(), 'Plumber' )['openingHoursSpecification'] ?? array();
check( 7 === count( $hours ) && '00:00' === $hours[0]['opens'] && '23:59' === $hours[0]['closes'], 'Un 24×7 elegido a propósito se publica (puede ser real): ' . json_encode( $hours ) );
$info = array(); foreach ( $B::warnings() as $w ) if ( 'local_24x7' === $w['code'] ) $info = $w;
check( 'info' === ( $info['level'] ?? '' ), 'El 24×7 es informativo: no sale en el aviso del Escritorio.' );
$GLOBALS['settings']['seo_local_schedule'] = array();
$GLOBALS['settings']['seo_local_hours'] = 'Mo-Su 00:00-23:59';
check( in_array( 'local_24x7', codes(), true ), 'También el 24×7 del campo de texto anterior.' );
$week = array(); foreach ( array_keys( Digitalisimo_Integrations_Local_Business::days() ) as $day ) $week[ $day ] = in_array( $day, array( 'sat', 'sun' ), true ) ? array( 'state' => 'closed' ) : array( 'state' => 'open', 'from' => '09:00', 'to' => '18:00' );
$GLOBALS['settings']['seo_local_schedule'] = $week;
$biz = node( graph(), 'Plumber' );
check( ! in_array( 'local_24x7', codes(), true ) && 5 === count( $biz['openingHoursSpecification'] ?? array() ), 'Un horario real se publica sin aviso.' );

// 4. sameAs.
check( in_array( 'same_as_empty', codes(), true ), 'Sin perfiles oficiales se avisa.' );
$GLOBALS['settings']['seo_social_profiles'] = array( 'instagram' => 'https://instagram.com/plomeria.ejemplo' );
check( in_array( 'same_as_gbp', codes(), true ) && ! in_array( 'same_as_empty', codes(), true ), 'Con SEO local y sin Google Business se avisa.' );
$GLOBALS['settings']['seo_social_profiles']['google_business'] = 'https://g.page/plomeria-ejemplo';
$org = node( graph(), 'Plumber' );
check( array( 'https://instagram.com/plomeria.ejemplo', 'https://g.page/plomeria-ejemplo' ) === ( $org['sameAs'] ?? null ), 'sameAs lleva las redes y Google Business: ' . json_encode( $org['sameAs'] ?? null ) );
check( ! array_intersect( array( 'same_as_empty', 'same_as_gbp' ), codes() ), 'Con los perfiles completos no hay aviso.' );
$GLOBALS['settings']['seo_social_profiles'] = array();
$GLOBALS['settings']['seo_knowledge_urls'] = "https://maps.google.com/?cid=123\nhttps://facebook.com/plomeria.ejemplo";
check( 'https://maps.google.com/?cid=123' === Digitalisimo_Integrations_Social_Profiles::values()['google_business'], 'Un enlace de maps.google.com se reconoce como Google Business.' );

// 5. Texto fijo en las plantillas de título.
$GLOBALS['settings']['seo_title_page'] = '%title% 🤖 Asegura tu Éxito Hoy';
$GLOBALS['settings']['seo_title_post'] = '%title% %sep% %sitename%';
$GLOBALS['settings']['seo_type_templates'] = array( 'product' => array( 'title' => '%title% | Envío Gratis Hoy' ) );
check( false !== strpos( message( 'template_literal' ), '«🤖 Asegura tu Éxito Hoy»' ) && false !== strpos( message( 'template_literal' ), 'páginas' ), 'Se avisa el texto fijo de la plantilla de páginas: ' . message( 'template_literal' ) );
check( 2 === count( array_keys( codes(), 'template_literal', true ) ), 'Y el de la plantilla de productos; la de entradas (%title% %sep% %sitename%) no.' );
check( '' === $B::template_literal( '%focuskw% %sep% %excerpt%' ) && '' === $B::template_literal( '%title% | %date(Y)%' ), 'Variables y separadores no son texto fijo.' );
check( '%title% 🤖 Asegura tu Éxito Hoy' === $GLOBALS['settings']['seo_title_page'], 'La plantilla del sitio no se cambia.' );
// Palabras de unión, separadores y el nombre del sitio escrito a mano no son relleno.
$GLOBALS['settings']['seo_site_name'] = 'Plomería Ejemplo 🥇';
foreach ( array( '%title% en %sitename%', '%title% de %category% y %sitename%', '%title% - %sitename%', '%title% | Plomería Ejemplo', '%title% | Plomería Ejemplo 🥇', '%title% | plomería ejemplo', '%title% · Plomería Ejemplo S.A. 🔧' ) as $tpl ) check( '' === $B::template_literal( $tpl ), "Sin texto fijo que avisar: {$tpl} → «" . $B::template_literal( $tpl ) . '»' );
check( 'en Ciudad Ejemplo' === $B::template_literal( '%title% en Ciudad Ejemplo | Plomería Ejemplo' ), 'Lo que no es unión ni el nombre sí se avisa: «' . $B::template_literal( '%title% en Ciudad Ejemplo | Plomería Ejemplo' ) . '»' );

if ( $fails ) { fwrite( STDERR, "{$fails} falla(s).\n" ); exit( 1 ); }
echo "SEO: schema sin datos inventados (nombre sin emoji, sin campos vacíos, sameAs) y avisos de marca y de plantillas.\n";
