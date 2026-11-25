<?php
/**
 * A5 de la auditoría SEO (2026-11-24), corregido tras la revisión del 2026-11-25: páginas con shortcodes sin resolver,
 * sin WordPress. En digitalisimo.lat /cuenta/, /ingresar/ y otras mostraban `[wpuf_account]` o
 * `[tutor_student_registration_form]` de plugins desinstalados. La primera versión marcaba también páginas sanas
 * (etiquetas dinámicas de Elementor, campos de formularios, texto entre corchetes); esta prueba lo impide:
 *   - forma de shortcode real, en el texto que se ve (en Elementor, sólo los campos de texto/HTML/shortcode);
 *   - nombre de un plugin conocido o que estuvo registrado; nunca elementor-tag, field ni all-fields;
 *   - sin muestra del front no se decide (shortcodes que sólo se registran en el front u organizadores de carga);
 *   - por omisión sólo avisa; con el ajuste encendido aplica, salvo «Mantener indexable» o si supera el tope.
 */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['options'] = array();
$GLOBALS['wp_options'] = array();
$GLOBALS['meta']    = array();
$GLOBALS['hooks']   = array();
$GLOBALS['shortcode_tags'] = array( 'contact-form-7' => 'x', 'gallery' => 'x' );
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][] = $hook; }
function is_admin() { return false; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function get_option( $key, $default = false ) { return $GLOBALS['wp_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['wp_options'][ $key ] = $value; return true; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function is_feed() { return false; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key, $default = null ) { return $GLOBALS['options'][ $key ] ?? $default; } }
require __DIR__ . '/../digitalisimo-seo/includes/class-index-hygiene.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$h = Digitalisimo_Integrations_Index_Hygiene::class;

// Forma de shortcode real.
check( array( 'wpuf_account' ) === $h::shortcode_tags_in( '<p>Hola</p>[wpuf_account]' ), 'Un shortcode suelto.' );
check( array( 'tutor_student_registration_form', 'gallery' ) === $h::shortcode_tags_in( '[tutor_student_registration_form role="x"] y [gallery ids="1,2"/]' ), 'Con atributos con nombre y autocerrado.' );
check( array( 'caja' ) === $h::shortcode_tags_in( '[caja tipo=aviso]texto[/caja]' ), 'Con cierre, el cierre no es otra etiqueta.' );
$sane = '[Nota del editor] Precio en [MXN] o [ES]. Descarga el [pdf ver]. Lee [texto](https://ejemplo.test). [Ver video] '
	. '<style>a[hidden]{display:none}</style> a[hidden] Nota [1], casilla [x], lista [ ] y [[escapado]] <code>[ejemplo]</code> <!-- wp:gallery {"ids":[1,2]} -->';
check( array() === $h::shortcode_tags_in( $sane ), 'Texto normal entre corchetes no tiene forma de shortcode: ' . json_encode( $h::shortcode_tags_in( $sane ) ) );

// Nombre: plugin conocido o que estuvo registrado; nunca elementor-tag, field ni all-fields.
check( $h::known_plugin_tag( 'wpuf_account' ) && $h::known_plugin_tag( 'tutor_student_registration_form' ) && $h::known_plugin_tag( 'rank_math_html_sitemap' ) && ! $h::known_plugin_tag( 'pdf' ), 'Lista de familias de plugins conocidos.' );
check( array( 'wpuf_account' ) === $h::unresolved( array( 'wpuf_account', 'contact-form-7', 'embed', 'pdf', 'elementor-tag', 'field', 'all-fields' ), array( 'contact-form-7' ) ), 'Sólo queda el de un plugin conocido que no está registrado.' );
check( array( 'mi_formulario' ) === $h::unresolved( array( 'mi_formulario', 'otra_cosa' ), array(), array( 'mi_formulario' ) ), 'Un shortcode que estuvo registrado en este sitio cuenta aunque no esté en la lista.' );
check( array() === $h::unresolved( array( 'elementor-tag', 'field', 'all-fields' ), array(), array( 'elementor-tag', 'field', 'all-fields' ) ), 'elementor-tag, field y all-fields nunca cuentan.' );

// Elementor: sólo los campos de texto, HTML y shortcode de sus widgets.
$elementor = array( array( 'elType' => 'container', 'elements' => array(
	array( 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>[Nota del editor] precio en [MXN]</p>', '__dynamic__' => array( 'editor' => '[elementor-tag id="a1" name="post-title" settings="%7B%7D"]' ) ) ),
	array( 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( '__dynamic__' => array( 'link' => '[elementor-tag id="b2" name="site-url" settings="%7B%7D"]' ), 'title' => 'Hola' ) ),
	array( 'elType' => 'widget', 'widgetType' => 'form', 'settings' => array( 'email_content' => '[all-fields] [field id="name"] [wpuf_account]', 'form_fields' => array( array( 'custom_id' => 'name' ) ) ) ),
	array( 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => '[wpuf_account]' ) ),
) ) );
$text = $h::elementor_text( $elementor );
check( array() === $h::reasons( $text, array(), array( 'elementor-tag', 'field', 'all-fields' ) ), 'Una página de Elementor con etiquetas dinámicas, un formulario y texto con corchetes no se marca: ' . json_encode( $h::shortcode_tags_in( $text ) ) );
$broken = $elementor; $broken[0]['elements'][] = array( 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => array( 'shortcode' => '[wpuf_account]' ) );
check( array( 'shortcode:wpuf_account' ) === $h::reasons( $h::elementor_text( $broken ), array() ), 'Un widget de shortcode con un plugin desinstalado sí se marca.' );

// Sin lista de registrados no se juzga.
check( array() === $h::reasons( '[wpuf_account]', null ), 'Sin la muestra del front no se decide nada.' );

// Muestra del front: decide con las del front aunque esta petición no las tenga.
$GLOBALS['wp_options'][ $h::CLOCK ] = array( 'changed' => time() - 60 );
check( null === $h::registered(), 'Tras un cambio de plugins, sin muestra del front no se decide.' );
$GLOBALS['shortcode_tags']['solo_front'] = 'x';
$h::sample_front();
unset( $GLOBALS['shortcode_tags']['solo_front'] );
$GLOBALS['wp_options'][ $h::CLOCK ]['at'] = time() + 1; // La muestra es posterior al cambio.
$registered = $h::registered();
check( is_array( $registered ) && in_array( 'solo_front', $registered, true ) && in_array( 'solo_front', get_option( $h::HISTORY ), true ), 'Un shortcode que sólo se registra en el front cuenta como registrado.' );
check( array() === $h::reasons( '[solo_front]', $registered, get_option( $h::HISTORY ) ), 'Por eso no se marca desde WP-Cron.' );
$GLOBALS['wp_options'][ $h::CLOCK ] = array( 'changed' => time() - 2 * DAY_IN_SECONDS );
check( is_array( $h::registered() ), 'Un sitio sin visitas decide con WP-Cron pasado un día.' );

// Tope de seguridad.
check( ! $h::over_cap( 3, 100 ) && $h::over_cap( 21, 1000 ) && $h::over_cap( 11, 100 ) && ! $h::over_cap( 10, 100 ) && ! $h::over_cap( 1, 10 ) && $h::over_cap( 2, 10 ) && ! $h::over_cap( 0, 0 ), 'Más de 20 o del 10 % de las publicadas espera confirmación.' );

// Lectura: por omisión sólo avisa; encendido aplica, salvo lo indexable a propósito o por encima del tope.
$GLOBALS['meta'] = array( 10 => array( $h::META => array( 'shortcode:wpuf_account' ) ), 11 => array( $h::META => array( 'shortcode:wpuf_login' ), $h::LOCK => '1' ), 12 => array() );
check( false === $h::auto_noindex( 10 ) && array() === $h::sitemap_clause(), 'De fábrica sólo avisa: ni noindex ni fuera del sitemap.' );
$GLOBALS['options']['seo_noindex_unresolved'] = 1;
check( true === $h::auto_noindex( 10 ), 'Encendido, lo detectado lleva noindex.' );
check( false === $h::auto_noindex( 11 ) && false === $h::auto_noindex( 12 ), 'Lo indexable a propósito y lo no detectado no cambian.' );
$clause = $h::sitemap_clause();
check( 'OR' === $clause['relation'] && $h::META === $clause[0]['key'] && 'NOT EXISTS' === $clause[0]['compare'] && $h::LOCK === $clause[1]['key'], 'El sitemap excluye lo detectado salvo lo indexable a propósito.' );
$GLOBALS['wp_options'][ $h::APPLY ] = 0;
check( false === $h::auto_noindex( 10 ) && array() === $h::sitemap_clause(), 'Por encima del tope no se aplica nada.' );

// Nada en el front salvo la muestra: la detección engancha guardado, cron y escritorio.
$h::init();
foreach ( array( 'wp', 'template_redirect', 'wp_head', 'the_content', 'parse_request', 'pre_get_posts' ) as $front ) check( ! in_array( $front, $GLOBALS['hooks'], true ), 'La detección no corre al servir: ' . $front );
check( in_array( 'save_post', $GLOBALS['hooks'], true ) && in_array( $h::CRON, $GLOBALS['hooks'], true ) && in_array( 'wp_footer', $GLOBALS['hooks'], true ), 'Se revisa al guardar y en segundo plano; el front sólo da su muestra.' );
check( 25 === $h::BATCH, 'Lotes de 25.' );

// Integración en SEO: robots, sitemap, editor y paridad sitio/red.
$root  = __DIR__ . '/../digitalisimo-seo/';
$suite = file_get_contents( $root . 'includes/class-seo-suite.php' );
$set   = file_get_contents( $root . 'includes/class-settings.php' );
$main  = file_get_contents( $root . 'digitalisimo-integrations.php' );
$src   = file_get_contents( $root . 'includes/class-index-hygiene.php' );
check( false !== strpos( $suite, "Digitalisimo_Integrations_Index_Hygiene::auto_noindex( \$id )" ), 'El robots del contenido consulta la detección.' );
check( false !== strpos( $suite, 'is_account_page()' ), '«Mi cuenta» lleva noindex con «Carrito, checkout, gracias y mi cuenta».' );
check( false === strpos( $src, "'woo:" ), 'WooCommerce no se guarda: se reconoce en vivo por la página asignada (cambiar su ajuste rige al instante).' );
check( false !== strpos( $suite, 'Digitalisimo_Integrations_Index_Hygiene::sitemap_clause()' ), 'El sitemap excluye lo detectado.' );
check( false !== strpos( $suite, 'digitalisimo_native_index_lock' ), 'El editor ofrece «Mantener indexable».' );
check( 1 === preg_match( "/'seo_noindex_unresolved'\s*=>\s*0,/", $set ), 'El noindex por shortcode viene apagado de fábrica.' );
check( 3 <= substr_count( $set, "'seo_noindex_unresolved'" ), 'El sitio lo declara, lo sanea y lo pinta.' );
check( 1 === preg_match( "/save_network\(\).*'seo_noindex_unresolved'/", $suite ), 'La red lo guarda como casilla.' );
check( false !== strpos( $suite, "'seo_noindex_unresolved' => 'Sacar del índice" ), 'La red lo pinta.' );
check( false !== strpos( $main, "'Digitalisimo_Integrations_Index_Hygiene'" ) && false !== strpos( $main, "digitalisimo_integrations_lazy( 'Digitalisimo_Integrations_Index_Hygiene' )" ), 'El módulo se carga con el SEO activo.' );

echo "Shortcodes sin resolver: sin falsos positivos, sólo aviso de fábrica, tope y lista del front: correcto.\n";
