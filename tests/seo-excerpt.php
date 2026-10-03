<?php
/** El extracto escrito llena el snippet vacío y respeta una meta personalizada. */
define( 'ABSPATH', __DIR__ );
$post = (object) array( 'ID' => 468, 'post_type' => 'page', 'post_title' => 'Inicio', 'post_name' => 'inicio', 'post_excerpt' => 'Extracto breve del sitio.', 'post_content' => 'Contenido largo del sitio que no debe sustituir al extracto.' );
$meta = array();
class Digitalisimo_Integrations_Settings {
	public static function get( $key, $fallback = '' ) { return array( 'seo_title_page' => '%title%', 'seo_description_page' => '%excerpt%' )[ $key ] ?? $fallback; }
}
class Digitalisimo_Media_Field { public static function render( $id, $name, $value ) {} }
class Digitalisimo_Integrations_Editorial { public static function editor_panels( $post ) {} }
class Digitalisimo_Integrations_Schema_Audit { public static function editor_button( $post ) {} }
function wp_nonce_field( $action, $name ) {}
function get_post_meta( $id, $key, $single = true ) { global $meta; return $meta[ $key ] ?? ''; }
function get_post( $id ) { global $post; return $post; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function strip_shortcodes( $value ) { return preg_replace( '/\[[^\]]+\]/', '', $value ); }
function wp_trim_words( $value, $count, $more ) { return implode( ' ', array_slice( explode( ' ', $value ), 0, $count ) ); }
function get_bloginfo( $key ) { return 'description' === $key ? 'Descripción general del sitio.' : 'Sitio'; }
function get_permalink( $post ) { return 'https://ejemplo.test/'; }
function home_url( $path = '' ) { return 'https://ejemplo.test' . $path; }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_textarea( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function checked( $actual, $expected, $echo = true ) { return $actual === $expected ? 'checked' : ''; }
function selected( $actual, $expected, $echo = true ) { return $actual === $expected ? 'selected' : ''; }
function is_paged() { return false; }
function is_singular() { return true; }
function get_queried_object_id() { return 468; }
function get_the_title( $id ) { return 'Inicio'; }
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php';
$seo = Digitalisimo_Integrations_SEO_Suite::class;
ob_start(); $seo::metabox( $post ); $html = ob_get_clean();
if ( false === strpos( $html, '>Extracto breve del sitio.</textarea>' ) ) throw new RuntimeException( 'El campo Meta descripción debe llenarse con el extracto escrito.' );
$current = new ReflectionMethod( $seo, 'current' );
if ( 'Extracto breve del sitio.' !== $current->invoke( null )['description'] ) throw new RuntimeException( 'La meta pública debe priorizar el extracto escrito.' );
$meta['digitalisimo_seo_description'] = 'Descripción personalizada.';
ob_start(); $seo::metabox( $post ); $html = ob_get_clean();
if ( false === strpos( $html, '>Descripción personalizada.</textarea>' ) || false === strpos( $html, 'data-snippet-use-excerpt' ) || 'Descripción personalizada.' !== $current->invoke( null )['description'] ) throw new RuntimeException( 'La descripción personalizada se conserva y ofrece usar el extracto expresamente.' );
echo "SEO: extracto en campo y frontend, con prioridad para meta personalizada.\n";
