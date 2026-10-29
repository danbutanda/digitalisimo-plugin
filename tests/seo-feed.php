<?php
/** RM16 y RM11: el texto del RSS sustituye sus variables y enlaces; el alt de un avatar nunca usa el correo. */
define( 'ABSPATH', __DIR__ );
function add_filter() {}
function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
function wp_kses_post( $v ) { return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $v ); }
function home_url( $p = '' ) { return 'https://sitio.test' . $p; }
function get_bloginfo( $k ) { return 'name' === $k ? 'Sitio & Co' : 'Lema'; }
function get_permalink( $p ) { return 'https://sitio.test/entrada/'; }
function get_the_title( $p ) { return $p->post_title; }
function get_post( $id = null ) { return new WP_Post(); }
function get_author_posts_url( $id ) { return 'https://sitio.test/autor/ana/'; }
function is_email( $v ) { return false !== strpos( (string) $v, '@' ); }
$users = array( 3 => (object) array( 'ID' => 3, 'display_name' => 'Ana <b>Pérez</b>' ) );
function get_userdata( $id ) { global $users; return $users[ $id ] ?? false; }
function get_user_by( $field, $value ) { global $users; return 'ana@sitio.test' === $value ? $users[3] : false; }
class WP_User { public $display_name = 'Usuario'; }
class WP_Post { public $ID = 7; public $post_author = 3; public $post_title = 'Hola <b>mundo</b>'; }
class WP_Comment { public $user_id = 0; public $comment_author = 'Visitante'; }
class Digitalisimo_Integrations_SEO_Resolver { public static $o = array(); public static function option( $k, $d = '' ) { return self::$o[ $k ] ?? $d; } }
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-variables.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-feed.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$f    = Digitalisimo_Integrations_SEO_Feed::class;
$post = new WP_Post();

check( '' === $f::render( '   ', $post ), 'Sin texto no se añade nada.' );
$out = $f::render( "La entrada %post_link% apareció primero en %site_link%.\nDe %author_link% · %title% %desconocida%", $post );
check( 'La entrada <a href="https://sitio.test/entrada/">Hola mundo</a> apareció primero en <a href="https://sitio.test/">Sitio &amp; Co</a>. De <a href="https://sitio.test/autor/ana/">Ana Pérez</a> · Hola mundo' === $out, 'Enlaces del feed escapados, variables de título sustituidas y desconocidas quitadas: ' . $out );
Digitalisimo_Integrations_SEO_Resolver::$o['seo_site_name'] = 'Marca';
check( false !== strpos( $f::render( '%sitedesc_link%', $post ), '>Marca - Lema</a>' ), 'El enlace con descripción usa el nombre SEO.' );
check( '<b>Fuente</b>' === $f::sanitize( '<b>Fuente</b><script>alert(1)</script>' ), 'El texto del feed se sanitiza como HTML de entradas.' );
check( 2000 === strlen( $f::sanitize( str_repeat( 'a', 5000 ) ) ), 'El texto del feed tiene un límite.' );

check( 'Ana Pérez' === $f::avatar_name( 3 ) && 'Ana Pérez' === $f::avatar_name( '3' ), 'Avatar por ID de usuario.' );
check( 'Ana Pérez' === $f::avatar_name( $post ), 'Avatar del autor de un contenido.' );
check( 'Visitante' === $f::avatar_name( new WP_Comment() ), 'Avatar de un comentario sin cuenta.' );
check( 'Ana Pérez' === $f::avatar_name( 'ana@sitio.test' ), 'Un correo con cuenta da su nombre público.' );
check( '' === $f::avatar_name( 'otro@sitio.test' ), 'Un correo sin cuenta nunca se usa como alt.' );
check( '<img alt="x">' === $f::avatar( '<img alt="x">', 3, 96, '', 'x' ), 'Un alt dado por el tema se respeta.' );
check( '<img alt="">' === $f::avatar( '<img alt="">', 3 ), 'Desactivado no cambia nada.' );

echo "Feed y avatares: texto del RSS con enlaces escapados y alt sólo con el nombre público.\n";
