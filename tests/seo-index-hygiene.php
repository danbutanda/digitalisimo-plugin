<?php
/**
 * A5 de la auditoría SEO (2026-11-24): páginas técnicas y con shortcodes sin resolver fuera del índice por omisión,
 * sin WordPress. En digitalisimo.lat /pago/, /gracias/, /cuenta/, /ingresar/ y unas diez más eran indexables y estaban
 * en el sitemap mostrando `[wpuf_account]` o `[tutor_student_registration_form]` de plugins ya desinstalados.
 *   - Detección: un shortcode cuya etiqueta no está registrada; WooCommerce por su página asignada, su shortcode o su
 *     bloque. Nunca al servir una página (la clase no engancha nada del front).
 *   - Lo marcado «Mantener indexable» gana: sólo se avisa. Apagado el ajuste, nada cambia.
 *   - El sitemap y el robots las excluyen; mi cuenta lleva noindex también por is_account_page().
 *   - El ajuste existe en el sitio y en la red (paridad).
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array();
$GLOBALS['meta']    = array();
$GLOBALS['hooks']   = array();
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = $hook; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key, $default = null ) { return $GLOBALS['options'][ $key ] ?? $default; } }
require __DIR__ . '/../digitalisimo-seo/includes/class-index-hygiene.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$h = Digitalisimo_Integrations_Index_Hygiene::class;

// Etiquetas que aparecen en un contenido.
check( array( 'wpuf_account' ) === $h::shortcode_tags_in( '<p>Hola</p>[wpuf_account]' ), 'Encuentra un shortcode suelto.' );
check( array( 'tutor_student_registration_form', 'gallery' ) === $h::shortcode_tags_in( '[tutor_student_registration_form role="x"] y [gallery ids="1,2"/]' ), 'Encuentra shortcodes con atributos y autocerrados.' );
check( array( 'caja' ) === $h::shortcode_tags_in( '[caja]texto[/caja]' ), 'El cierre no es otra etiqueta.' );
check( array() === $h::shortcode_tags_in( 'Nota [1], casilla [x], lista [ ] y [[escapado]]' ), 'Las notas, casillas y escapados no son shortcodes.' );
check( array() === $h::shortcode_tags_in( '<code>[ejemplo]</code><pre>[otro attr=1]</pre>' ), 'Lo que está dentro de <code> o <pre> es texto.' );
check( array() === $h::shortcode_tags_in( '<!-- wp:gallery {"ids":[1,2],"x":["a"]} -->' ), 'Los delimitadores de bloques no cuentan.' );
check( array() === $h::shortcode_tags_in( 'Sin corchetes' ), 'Sin corchetes no hay nada que buscar.' );

// Sin resolver: lo que no está registrado; los del núcleo siempre valen.
check( array( 'wpuf_account' ) === $h::unresolved( array( 'wpuf_account', 'contact-form-7', 'embed' ), array( 'contact-form-7' ) ), 'Sólo queda sin resolver la etiqueta no registrada.' );

// Motivos.
check( array( 'shortcode:wpuf_account' ) === $h::reasons( '[wpuf_account]', '', array( 'otro' ) ), 'Un shortcode de un plugin desinstalado es motivo.' );
check( array() === $h::reasons( '[wpuf_account]', '', array( 'wpuf_account' ) ), 'Con el plugin activo no es motivo.' );
check( array() === $h::reasons( '[wpuf_account]', '', null ), 'Sin la lista de registrados (aún no hubo pasada) no se juzga ningún shortcode.' );
check( array( 'woo:checkout' ) === $h::reasons( 'Pagar', 'checkout', array() ), 'La página de pago asignada en WooCommerce es técnica.' );
check( array( 'woo:woocommerce_my_account' ) === $h::reasons( '[woocommerce_my_account]', '', array( 'woocommerce_my_account' ) ), 'Una segunda página «Mi cuenta» con su shortcode también es técnica.' );
check( array( 'woo:woocommerce/checkout' ) === $h::reasons( '<!-- wp:woocommerce/checkout {"a":1} --><div></div><!-- /wp:woocommerce/checkout -->', '', array() ), 'Una página con el bloque de pago es técnica.' );
check( array() === $h::reasons( '<p>Un artículo normal con [contact-form-7 id="1"]</p>', '', array( 'contact-form-7' ) ), 'Un contenido normal no tiene motivos.' );
check( false !== strpos( $h::label( 'shortcode:wpuf_account' ), '[wpuf_account]' ) && false !== strpos( $h::label( 'woo:myaccount' ), 'Mi cuenta' ), 'Los motivos se explican en palabras.' );

// Lectura: respeta «Mantener indexable» y el interruptor.
$GLOBALS['meta'] = array( 10 => array( $h::META => array( 'shortcode:wpuf_account' ) ), 11 => array( $h::META => array( 'woo:checkout' ), $h::LOCK => '1' ), 12 => array() );
check( true === $h::auto_noindex( 10 ), 'Lo detectado lleva noindex.' );
check( false === $h::auto_noindex( 11 ), 'Lo que el dueño mantiene indexable no se pisa.' );
check( false === $h::auto_noindex( 12 ), 'Lo no detectado no cambia.' );
$clause = $h::sitemap_clause();
check( 'OR' === $clause['relation'] && $h::META === $clause[0]['key'] && 'NOT EXISTS' === $clause[0]['compare'] && $h::LOCK === $clause[1]['key'], 'El sitemap excluye lo detectado salvo lo indexable a propósito.' );
$GLOBALS['options']['seo_noindex_technical'] = 0;
check( false === $h::auto_noindex( 10 ) && array() === $h::sitemap_clause(), 'Apagado, nada cambia en el robots ni en el sitemap.' );
$GLOBALS['options'] = array();

// Nada en el front: la detección engancha guardado, cron y escritorio, nunca la petición de una página.
$h::init();
foreach ( array( 'wp', 'template_redirect', 'wp_head', 'the_content', 'parse_request', 'pre_get_posts' ) as $front ) check( ! in_array( $front, $GLOBALS['hooks'], true ), 'La detección no corre al servir: ' . $front );
check( in_array( 'save_post', $GLOBALS['hooks'], true ) && in_array( $h::CRON, $GLOBALS['hooks'], true ), 'Se revisa al guardar y en segundo plano.' );

// Integración en SEO: robots, sitemap, editor y paridad sitio/red.
$root  = __DIR__ . '/../digitalisimo-seo/';
$suite = file_get_contents( $root . 'includes/class-seo-suite.php' );
$set   = file_get_contents( $root . 'includes/class-settings.php' );
$main  = file_get_contents( $root . 'digitalisimo-integrations.php' );
check( false !== strpos( $suite, "Digitalisimo_Integrations_Index_Hygiene::auto_noindex( \$id )" ), 'El robots del contenido consulta la detección.' );
check( false !== strpos( $suite, 'is_account_page()' ), '«Mi cuenta» lleva noindex con «Carrito, checkout, gracias y mi cuenta».' );
check( false !== strpos( $suite, 'Digitalisimo_Integrations_Index_Hygiene::sitemap_clause()' ), 'El sitemap excluye lo detectado.' );
check( false !== strpos( $suite, 'digitalisimo_native_index_lock' ), 'El editor ofrece «Mantener indexable».' );
check( 1 === preg_match( "/'seo_noindex_technical'\s*=>\s*1,/", $set ), 'El ajuste viene encendido por omisión.' );
check( 3 <= substr_count( $set, "'seo_noindex_technical'" ), 'El sitio lo declara, lo sanea y lo pinta.' );
check( 1 === preg_match( "/save_network\(\).*'seo_noindex_technical'/", $suite ), 'La red lo guarda como casilla.' );
check( 1 === preg_match( "/network_site_fields.*'seo_noindex_technical'|'seo_noindex_technical' => 'Páginas técnicas/", $suite ), 'La red lo pinta.' );
check( false !== strpos( $main, "'Digitalisimo_Integrations_Index_Hygiene'" ) && false !== strpos( $main, "digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Index_Hygiene' )" ), 'El módulo se carga con el SEO activo.' );

echo "Páginas técnicas y shortcodes sin resolver fuera del índice, respetando lo indexable a propósito: correcto.\n";
