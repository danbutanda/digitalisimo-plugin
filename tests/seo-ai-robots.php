<?php
/** La política robots genera únicamente excepciones reales y no mezcla sitios. */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['blog_id'] = 1;
$GLOBALS['site_options'] = array( 1 => array(), 2 => array() );
$GLOBALS['network_options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['content_paths'] = array( 1 => array(), 2 => array() );
function apply_filters( $name, $value ) { return $value; }
function is_multisite() { return true; }
function get_site_option( $name, $default = array() ) { return $GLOBALS['network_options']; }
function get_post_types() { return array( 'page', 'post' ); }
function get_posts( $query ) { return $GLOBALS['content_paths'][ $GLOBALS['blog_id'] ][ $query['meta_key'] ][ $query['meta_value'] ] ?? array(); }
function get_permalink( $id ) { return 'https://sitio' . $GLOBALS['blog_id'] . '.test/pagina-' . $id . '/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $GLOBALS['blog_id'] ][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $GLOBALS['blog_id'] ][ $key ] = $value; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $GLOBALS['blog_id'] ][ $key ] ); }
class Digitalisimo_Integrations_Settings {
	public static function get( $key, $default = null ) { return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default ); }
}
class Digitalisimo_Integrations_SEO_Resolver {
	public static function option( $key, $default = null ) { return $GLOBALS['site_options'][ $GLOBALS['blog_id'] ][ $key ] ?? $GLOBALS['network_options'][ $key ] ?? $default; }
	public static function option_with_origin( $key, $default = null ) { return array( 'value' => self::option( $key, $default ), 'label' => 'Prueba' ); }
}
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-ai.php';
foreach ( Digitalisimo_Integrations_SEO_AI::crawlers() as $id => $crawler ) if ( 'allow' !== Digitalisimo_Integrations_SEO_AI::crawler_rule( $id, $crawler ) ) throw new RuntimeException( 'Todos los rastreadores deben estar permitidos por defecto: ' . $id );
$base = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
$render = Digitalisimo_Integrations_SEO_AI::robots( $base, true );
if ( $base !== $render || 1 !== substr_count( $render, 'User-agent:' ) ) throw new RuntimeException( 'Por defecto sólo debe existir el grupo general de WordPress.' );
$GLOBALS['site_options'][1]['seo_ai_crawler_rules'] = '{"gptbot":"disallow"}';
$blocked = Digitalisimo_Integrations_SEO_AI::robots( $base, true );
if ( false === strpos( $blocked, "User-agent: GPTBot\nDisallow: /" ) || false !== strpos( $blocked, 'User-agent: ClaudeBot' ) ) throw new RuntimeException( 'Sólo los bloqueos configurados expresamente deben crear grupos.' );
if ( false !== strpos( $blocked, "User-agent: GPTBot\nDisallow: /\nAllow: /wp-admin/admin-ajax.php" ) ) throw new RuntimeException( 'El bloqueo total no debe reabrir admin-ajax.' );
$manual = $base . "\nUser-agent: GPTBot\nDisallow: /manual/\n";
$manual_render = Digitalisimo_Integrations_SEO_AI::robots( $manual, true );
if ( 1 !== substr_count( $manual_render, 'User-agent: GPTBot' ) ) throw new RuntimeException( 'Una excepción manual no debe duplicarse.' );
$GLOBALS['site_options'][1]['seo_ai_crawler_rules'] = '';
$GLOBALS['site_options'][1]['seo_ai_google_extended_policy'] = 'disallow';
$render = Digitalisimo_Integrations_SEO_AI::robots( $base, true );
if ( false === strpos( $render, "User-agent: Google-Extended\nDisallow: /" ) ) throw new RuntimeException( 'Google-Extended debe bloquearse sólo por su opción independiente.' );
$GLOBALS['site_options'][1]['seo_ai_google_extended_policy'] = 'allow';
$GLOBALS['content_paths'][1]['digitalisimo_seo_ai_search']['restrict'] = array( 17 );
$GLOBALS['transients'] = array();
$with_custom_general = $base . "\nUser-agent: *\nDisallow: /privado/\n";
$render = Digitalisimo_Integrations_SEO_AI::robots( $with_custom_general, true );
if ( false === strpos( $render, "User-agent: OAI-SearchBot\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nDisallow: /privado/\nDisallow: /pagina-17/" ) ) throw new RuntimeException( 'La excepción de búsqueda debe conservar todas las reglas generales.' );
$GLOBALS['site_options'][2]['seo_ai_google_extended_policy'] = 'disallow';
$GLOBALS['blog_id'] = 2;
$render = Digitalisimo_Integrations_SEO_AI::robots( $base, true );
if ( false !== strpos( $render, '/pagina-17/' ) || false === strpos( $render, "User-agent: Google-Extended\nDisallow: /" ) ) throw new RuntimeException( 'La política y las URLs deben aislarse por sitio.' );
if ( $base !== Digitalisimo_Integrations_SEO_AI::robots( $base, false ) ) throw new RuntimeException( 'Un sitio privado no debe emitir grupos IA.' );
echo "SEO AI robots: acceso general predeterminado, excepciones explícitas y aislamiento correctos.\n";
