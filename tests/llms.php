<?php
/**
 * llms.txt: Markdown con H1 y enlaces, generado con los datos de cada sitio,
 * validado antes de servirse y con respaldo automático si el manual no es válido.
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['blog'] = 1;
$GLOBALS['settings'] = array( 1 => array(), 2 => array() );
$GLOBALS['sites'] = array(
	1 => array( 'name' => 'Agencia Uno', 'description' => 'Marketing & SEO en México', 'home' => 'https://uno.test/', 'pages' => array( 10 => 'Servicios', 11 => 'Contacto', 12 => 'Privada', 13 => 'Inicio', 14 => 'Borrador [2026]' ), 'posts' => array( 20 => 'Guía [2026]' ), 'noindex' => array( 12 ), 'drafts' => array( 14 ), 'front' => 13, 'front_meta' => 'Agencia que integra marketing, tecnología e IA. [gallery ids="1"]', 'front_excerpt' => 'Ofrecemos <b>marketing</b>, software y sitios web.' ),
	2 => array( 'name' => 'Tienda Dos', 'description' => '', 'home' => 'https://red.test/dos/', 'pages' => array( 30 => 'Catálogo' ), 'posts' => array(), 'noindex' => array(), 'drafts' => array(), 'front' => 0, 'front_meta' => '', 'front_excerpt' => '' ),
);
class Digitalisimo_Integrations_Settings { public static function get( $key ) { return $GLOBALS['settings'][ $GLOBALS['blog'] ][ $key ] ?? ( 'seo_ai_llms_mode' === $key ? 'automatic' : '' ); } }
function site() { return $GLOBALS['sites'][ $GLOBALS['blog'] ]; }
function get_bloginfo( $what ) { return 'name' === $what ? site()['name'] : site()['description']; }
function home_url( $path = '' ) { return rtrim( site()['home'], '/' ) . $path; }
function get_option( $key ) { if ( 'show_on_front' === $key ) return site()['front'] ? 'page' : 'posts'; return 'page_on_front' === $key ? site()['front'] : 0; }
function get_post( $id ) { $all = site()['pages'] + site()['posts']; return isset( $all[ $id ] ) ? (object) array( 'ID' => $id, 'post_status' => in_array( $id, site()['drafts'], true ) ? 'draft' : 'publish' ) : null; }
function get_post_field( $field, $id ) { return $id === site()['front'] ? site()['front_excerpt'] : ''; }
function strip_shortcodes( $v ) { return preg_replace( '/\[[a-z_]+[^\]]*\]/', '', $v ); }
function get_posts( $args ) {
	$list = 'page' === $args['post_type'] ? site()['pages'] : site()['posts'];
	$out = array();
	foreach ( $list as $id => $title ) $out[] = (object) array( 'ID' => $id, 'post_title' => $title );
	return $out;
}
function get_permalink( $post ) { $id = is_object( $post ) ? $post->ID : $post; return home_url( '/p' . $id . '/' ); }
function get_the_title( $post ) { $id = is_object( $post ) ? $post->ID : $post; $all = site()['pages'] + site()['posts']; return htmlspecialchars( $all[ $id ] ?? '', ENT_QUOTES ); }
function get_post_meta( $id, $key ) { if ( 'digitalisimo_seo_description' === $key ) return $id === site()['front'] ? site()['front_meta'] : ''; return in_array( $id, site()['noindex'], true ) ? array( 'noindex' ) : ''; }
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

// Automático: H1, cita con la meta descripción de la portada, párrafo y «Páginas principales» con Inicio.
$text = $l::automatic();
$expected = "# Agencia Uno\n\n> Agencia que integra marketing, tecnología e IA.\n\nOfrecemos marketing, software y sitios web.\n\n## Páginas principales\n\n- [Inicio](https://uno.test/): Marketing & SEO en México\n";
check( $expected === $text, "Estructura esperada:\n" . $text );
check( false === strpos( $text, 'Servicios' ) && false === strpos( $text, 'Guía' ), 'Sin selección no se listan páginas ni entradas: ningún enlace se inventa.' );
check( false === strpos( $text, '[gallery' ) && false === strpos( $text, '<b>' ), 'Sin shortcodes ni HTML.' );
check( $l::validate( $text )['valid'] && 1 === $l::sections( $text ), 'La salida automática es válida y tiene una sección.' );

// Un extracto que repite la descripción no se publica como párrafo (caso real de producción).
check( $l::similar( 'Agencia Digital en México que integra marketing, tecnología e inteligencia artificial para impulsar el crecimiento de tu negocio.', 'Agencia de marketing digital en México que integra marketing, tecnología e inteligencia artificial para impulsar el crecimiento de tu negocio.' ), 'Casi idénticos.' );
check( ! $l::similar( 'Agencia que integra marketing, tecnología e IA.', 'Ofrecemos marketing, software y sitios web.' ) && ! $l::similar( '', 'x' ), 'Textos distintos o vacíos no son similares.' );
$GLOBALS['sites'][1]['front_excerpt'] = 'Agencia que integra marketing, tecnología e IA!';
check( false === strpos( $l::automatic(), "\n\nAgencia que integra marketing, tecnología e IA!\n" ), 'El párrafo repetido se omite.' );
$GLOBALS['sites'][1]['front_excerpt'] = 'Ofrecemos <b>marketing</b>, software y sitios web.';

// Páginas elegidas: sólo publicadas, indexables, de este sitio y sin ancla.
$GLOBALS['settings'][1] = array( 'seo_ai_llms_urls' => "https://uno.test/p11/\nhttps://uno.test/p12/\nhttps://uno.test/p14/\nhttps://otro.test/p10/\nhttps://uno.test/p10/#x\nhttps://uno.test/no-existe/\nhttps://uno.test/p11/", 'sitemap_enabled' => 1 );
$text = $l::automatic();
check( false !== strpos( $text, "- [Inicio](https://uno.test/): Marketing & SEO en México\n- [Contacto](https://uno.test/p11/)\n" ) && 1 === substr_count( $text, 'p11' ), 'Una página publicada elegida se lista una vez.' );
foreach ( array( 'p12' => 'noindex', 'p14' => 'borrador', 'otro.test' => 'otro dominio', '#x' => 'ancla', 'no-existe' => 'inexistente' ) as $needle => $why ) check( false === strpos( $text, $needle ), 'No se lista una página ' . $why . '.' );
check( false !== strpos( $text, "## Opcional\n\n- [Sitemap XML](https://uno.test/wp-sitemap.xml)" ) && 2 === $l::sections( $text ), 'El sitemap se enlaza si está activo.' );
check( "https://uno.test/p11/" === $l::sanitize_urls( "https://uno.test/p11/\njavascript:alert(1)\nftp://x.test/\nhttps://uno.test/p11/" ), 'Sólo URLs http(s) únicas.' );

// Sin descripción no se inventa texto; cada sitio usa sus datos y su home_url (subdirectorio).
$GLOBALS['blog'] = 2;
$text = $l::automatic();
check( "# Tienda Dos\n\n## Páginas principales\n\n- [Inicio](https://red.test/dos/): Página principal del sitio.\n" === $text, "Sitio 2 sin descripción:\n" . $text );
check( 'https://red.test/dos/llms.txt' === $l::endpoint(), 'El endpoint respeta el subdirectorio.' );
$_SERVER['REQUEST_URI'] = '/dos/llms.txt?x=1';
check( 'exact' === $l::requested(), 'La ruta del subsitio se reconoce.' );
$_SERVER['REQUEST_URI'] = '/dos/llms.txt/';
check( 'slash' === $l::requested(), 'Con barra final se reconoce para redirigir a la canónica.' );
$_SERVER['REQUEST_URI'] = '/llms.txt';
check( '' === $l::requested(), 'La raíz de la red no es el llms.txt del subsitio.' );
$GLOBALS['blog'] = 1;
$GLOBALS['settings'][1] = array();

// Diagnóstico de la respuesta real.
$ok = "# Sitio\n\n## Páginas principales\n\n- [Inicio](https://uno.test/)\n";
$d = $l::diagnose( 200, 'text/plain; charset=utf-8', $ok );
check( 'CORRECTO' === $d['state'] && $d['h1'] && 1 === $d['links'] && 1 === $d['sections'] && strlen( $ok ) === $d['bytes'], 'Respuesta correcta.' );
check( 'ERROR HTTP' === $l::diagnose( 301, 'text/html', '' )['state'] && 'ERROR HTTP' === $l::diagnose( 0, '', '', 'cURL error 28' )['state'], 'Redirección o error de red: ERROR HTTP.' );
check( 'ERROR HTTP' === $l::diagnose( 200, 'text/html; charset=UTF-8', '<!doctype html><title>x</title>' )['state'], 'Una página HTML con 200 es un error: es lo que hoy ve Lighthouse.' );
check( 'VACÍO' === $l::diagnose( 200, 'text/plain', "  \n" )['state'], 'Vacío.' );
check( 'FALTA H1' === $l::diagnose( 200, 'text/plain', "Sitio\n- [Inicio](https://uno.test/)" )['state'] && 'FALTA H1' === $l::diagnose( 200, 'text/plain', "# A\n# B\n- [x](https://uno.test/)" )['state'], 'Sin H1 o con dos H1.' );
check( 'SIN ENLACES' === $l::diagnose( 200, 'text/plain', "# Sitio\n\nTexto." )['state'], 'Sin enlaces.' );

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
