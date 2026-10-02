<?php
/** Reglas de robots.txt: texto seguro, herencia por sitio y salida sin duplicar sitemap. */
define( 'ABSPATH', __DIR__ );
$blog_id = 1;
$rules = array( 1 => "Disallow: /privado/\nSitemap: https://otro.test/mapa.xml", 2 => "User-agent: Googlebot\nAllow: /publico/" );
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) { global $filters; $filters[ $hook ][ $priority ] = $callback; }
class Digitalisimo_Integrations_SEO_Resolver {
	public static function option( $key, $default = '' ) { global $rules, $blog_id; return $rules[ $blog_id ] ?? $default; }
}
require __DIR__ . '/../digitalisimo-seo/includes/class-robots.php';
$robots = Digitalisimo_Integrations_Robots::class;
$clean = $robots::sanitize( "# Comentario\nDisallow: /niñez/\nSitemap: https://otro.test/mapa.xml\nUser-agent: Googlebot\nAllow: /publico/\n<script>alert(1)</script>" );
if ( false === strpos( $clean, 'User-agent: *' ) || false === strpos( $clean, 'Disallow: /niñez/' ) || false === strpos( $clean, 'User-agent: Googlebot' ) || false === strpos( $clean, 'Allow: /publico/' ) ) throw new RuntimeException( 'El editor debe conservar grupos y rutas válidas.' );
if ( false !== strpos( $clean, 'Sitemap:' ) || false !== strpos( $clean, '<script>' ) ) throw new RuntimeException( 'No debe admitir un sitemap duplicado ni HTML.' );
$base = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
$first = $robots::append( $base, true );
if ( false === strpos( $first, 'Disallow: /privado/' ) || false === strpos( $first, 'Disallow: /wp-admin/' ) || false !== strpos( $first, 'otro.test' ) ) throw new RuntimeException( 'Debe conservar WordPress, añadir reglas propias y rechazar otro sitemap.' );
$blog_id = 2;
$second = $robots::append( $base, true );
if ( false === strpos( $second, 'Allow: /publico/' ) || false !== strpos( $second, '/privado/' ) ) throw new RuntimeException( 'Las reglas de otro sitio no deben mezclarse.' );
if ( $base !== $robots::append( $base, false ) ) throw new RuntimeException( 'Un sitio privado no debe publicar reglas adicionales.' );
echo "robots.txt: sintaxis, conservación de reglas y aislamiento por sitio correctos.\n";
