<?php
/**
 * llms.txt: Markdown con H1 y enlaces, generado con los datos de cada sitio,
 * validado antes de servirse y con respaldo automático si el manual no es válido.
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['blog'] = 1;
$GLOBALS['settings'] = array( 1 => array(), 2 => array() );
$GLOBALS['sites'] = array(
	1 => array( 'name' => 'Agencia Uno', 'description' => 'Marketing & SEO en México', 'home' => 'https://uno.test/', 'pages' => array( 10 => 'Servicios', 11 => 'Contacto', 12 => 'Privada', 13 => 'Inicio' ), 'posts' => array( 20 => 'Guía [2026]' ), 'noindex' => array( 12 ), 'front' => 13 ),
	2 => array( 'name' => 'Tienda Dos', 'description' => '', 'home' => 'https://red.test/dos/', 'pages' => array( 30 => 'Catálogo' ), 'posts' => array(), 'noindex' => array(), 'front' => 0 ),
);
class Digitalisimo_Integrations_Settings { public static function get( $key ) { return $GLOBALS['settings'][ $GLOBALS['blog'] ][ $key ] ?? ( 'seo_ai_llms_mode' === $key ? 'automatic' : '' ); } }
function site() { return $GLOBALS['sites'][ $GLOBALS['blog'] ]; }
function get_bloginfo( $what ) { return 'name' === $what ? site()['name'] : site()['description']; }
function home_url( $path = '' ) { return rtrim( site()['home'], '/' ) . $path; }
function get_option( $key ) { return 'page_on_front' === $key ? site()['front'] : 0; }
function get_posts( $args ) {
	$list = 'page' === $args['post_type'] ? site()['pages'] : site()['posts'];
	$out = array();
	foreach ( $list as $id => $title ) $out[] = (object) array( 'ID' => $id, 'post_title' => $title );
	return $out;
}
function get_permalink( $post ) { $id = is_object( $post ) ? $post->ID : $post; return home_url( '/p' . $id . '/' ); }
function get_the_title( $post ) { $id = is_object( $post ) ? $post->ID : $post; $all = site()['pages'] + site()['posts']; return htmlspecialchars( $all[ $id ] ?? '', ENT_QUOTES ); }
function get_post_meta( $id, $key ) { return in_array( $id, site()['noindex'], true ) ? array( 'noindex' ) : ''; }
function post_password_required( $post ) { return false; }
function url_to_postid( $url ) { return preg_match( '~/p(\d+)/$~', $url, $m ) ? (int) $m[1] : 0; }
function esc_url_raw( $v ) { return filter_var( trim( (string) $v ), FILTER_VALIDATE_URL ) ? trim( (string) $v ) : ''; }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function untrailingslashit( $v ) { return rtrim( $v, '/' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function apply_filters( $hook, $value ) { return $GLOBALS['filter'] ?? $value; }

require __DIR__ . '/../digitalisimo-seo/includes/class-llms.php';
$l = Digitalisimo_Integrations_LLMS::class;
function check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }

// Validación.
check( $l::validate( "# Sitio\n\n- [Inicio](https://a.test/)\n" )['valid'], 'H1 y un enlace es válido.' );
check( ! $l::validate( "Sitio\n- [Inicio](https://a.test/)" )['valid'], 'Sin H1 al principio no es válido.' );
check( ! $l::validate( "# Sitio\n\nSin enlaces." )['valid'] && ! $l::validate( "# Sitio\n- [Roto](nota)" )['valid'], 'Sin enlace http(s) válido no es válido.' );
check( ! $l::validate( "# A\n# B\n- [x](https://a.test/)" )['valid'], 'Dos H1 no son válidos.' );
check( ! $l::validate( "# A\n<script>x</script>\n- [x](https://a.test/)" )['valid'], 'HTML no es válido.' );
check( '\\[2026\\]' === $l::link_text( '[2026]' ) && 'https://a.test/a%20b%28c%29' === $l::link_url( 'https://a.test/a b(c)' ), 'Textos y URLs se escapan para Markdown.' );

// Automático: datos del propio sitio, sólo contenido indexable, sin repetir la portada.
$text = $l::automatic();
check( 0 === strpos( $text, "# Agencia Uno\n\n> Marketing & SEO en México\n\n## Recursos\n\n- [Inicio](https://uno.test/)" ), 'H1, descripción y portada salen del sitio: ' . $text );
check( false !== strpos( $text, '- [Servicios](https://uno.test/p10/)' ) && false !== strpos( $text, '- [Contacto](https://uno.test/p11/)' ), 'Páginas publicadas incluidas.' );
check( false === strpos( $text, 'Privada' ) && false === strpos( $text, 'p13' ), 'Noindex y la página de inicio no se repiten.' );
check( false !== strpos( $text, "## Contenido reciente\n\n- [Guía \\[2026\\]](https://uno.test/p20/)" ), 'Contenido reciente con títulos escapados.' );
check( false === strpos( $text, 'Sitemap' ), 'Sin sitemap activo no se enlaza.' );
check( $l::validate( $text )['valid'], 'La salida automática es válida.' );

// URLs elegidas reemplazan la lista automática.
$GLOBALS['settings'][1] = array( 'seo_ai_llms_urls' => "https://uno.test/p11/\nno-es-url\nhttps://uno.test/p11/", 'sitemap_enabled' => 1 );
$text = $l::automatic();
check( 1 === substr_count( $text, 'p11' ) && false === strpos( $text, 'Servicios' ) && false === strpos( $text, 'Contenido reciente' ), 'Sólo las URLs elegidas, sin duplicados.' );
check( false !== strpos( $text, "## Opcional\n\n- [Sitemap XML](https://uno.test/wp-sitemap.xml)" ), 'El sitemap se enlaza si está activo.' );
check( "https://uno.test/p11/" === $l::sanitize_urls( "https://uno.test/p11/\njavascript:alert(1)\nftp://x.test/\nhttps://uno.test/p11/" ), 'Sólo URLs http(s) únicas.' );

// Multisite: cada sitio genera el suyo con su home_url (también en subdirectorio).
$GLOBALS['blog'] = 2;
$text = $l::automatic();
check( 0 === strpos( $text, "# Tienda Dos\n\n## Recursos\n\n- [Inicio](https://red.test/dos/)" ) && false !== strpos( $text, 'https://red.test/dos/p30/' ) && false === strpos( $text, 'uno.test' ), 'El sitio 2 usa sus propios datos y URLs.' );
check( 'https://red.test/dos/llms.txt' === $l::endpoint(), 'El endpoint respeta el subdirectorio.' );
$_SERVER['REQUEST_URI'] = '/dos/llms.txt?x=1';
check( $l::requested(), 'La ruta del subsitio se reconoce.' );
$_SERVER['REQUEST_URI'] = '/llms.txt';
check( ! $l::requested(), 'La raíz de la red no es el llms.txt del subsitio.' );
$GLOBALS['blog'] = 1;

// Modos: manual válido, manual inválido con respaldo, híbrido y filtro inválido ignorado.
$GLOBALS['settings'][1] = array( 'seo_ai_llms_mode' => 'manual', 'seo_ai_llms_manual' => "# Propio\n\n- [Blog](https://uno.test/blog/)" );
$result = $l::result();
check( $result['valid'] && ! $result['fallback'] && 0 === strpos( $result['content'], '# Propio' ), 'Un manual válido se publica tal cual.' );
$GLOBALS['settings'][1]['seo_ai_llms_manual'] = 'Texto sin título ni enlaces';
$result = $l::result();
check( $result['valid'] && $result['fallback'] && 0 === strpos( $result['content'], '# Agencia Uno' ) && $result['errors'], 'Un manual inválido nunca se publica: se sirve el automático y se informan los errores.' );
$GLOBALS['settings'][1] = array( 'seo_ai_llms_mode' => 'hybrid', 'seo_ai_llms_manual' => "## Extra\n\n- [Casos](https://uno.test/casos/)" );
$result = $l::result();
check( $result['valid'] && ! $result['fallback'] && false !== strpos( $result['content'], "## Extra" ) && 0 === strpos( $result['content'], '# Agencia Uno' ), 'Híbrido añade el manual al automático.' );
$GLOBALS['settings'][1]['seo_ai_llms_manual'] = "# Otro H1\n- [x](https://uno.test/x/)";
check( l_fallback(), 'Un híbrido con un segundo H1 no es válido y usa el automático.' );
function l_fallback() { $r = Digitalisimo_Integrations_LLMS::result(); return $r['fallback'] && false === strpos( $r['content'], 'Otro H1' ); }
$GLOBALS['settings'][1] = array();
$GLOBALS['filter'] = 'roto';
check( 0 === strpos( $l::result()['content'], '# Agencia Uno' ), 'Un filtro que rompe el formato se ignora.' );
check( 'automatic' === $l::sanitize_mode( 'otro' ) && 'hybrid' === $l::sanitize_mode( 'hybrid' ), 'Modo desconocido vuelve a automático.' );

echo "llms.txt: validación, generación por sitio, modos y respaldo correctos.\n";
