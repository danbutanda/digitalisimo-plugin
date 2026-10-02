<?php
define( 'ABSPATH', __DIR__ );
function absint( $value ) { return abs( (int) $value ); }
function get_current_blog_id() { global $blog_id; return $blog_id; }
function get_option( $key, $default = false ) { global $options; return $options[ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { global $options; $options[ $key ] = $value; return true; }
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_type' => 'page', 'post_status' => 'publish', 'post_modified_gmt' => '2026-10-02 10:00:00' ); }
function get_queried_object_id() { return 468; }
function is_singular( $type ) { return 'page' === $type; }
class Digitalisimo_Integrations_Performance_Manager { public static function frontend_safe() { return true; } }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { global $enabled; return 'perf_hero_critical' === $key && $enabled; } }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-hero.php';
$hero = 'Digitalisimo_Integrations_Performance_Hero';
$blog_id = 7;
$enabled = true;
$assert = function( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); };
$elements = array( array( 'id' => '758bbbb7', 'elType' => 'container', 'settings' => array(), 'elements' => array(
	array( 'id' => '41420ebb', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'header_size' => 'h1', 'title' => 'Título visible' ), 'elements' => array() ),
) ) );
$ids = $hero::detect( $elements );
$assert( array( 'parent' => '758bbbb7', 'heading' => '41420ebb' ) === $ids, 'Debe detectar el primer H1 y su contenedor.' );
$page_css = '.elementor-468 .elementor-element.elementor-element-758bbbb7{--display:flex;--align-items:center;--padding-top:25px;--overflow:hidden}'
	. '.elementor-468 .elementor-element.elementor-element-41420ebb{text-align:center}'
	. '.elementor-468 .elementor-element.elementor-element-41420ebb .elementor-heading-title{font-family:"Source Serif 4",Sans-serif;font-size:72px;font-weight:400;line-height:76px;color:var( --e-global-color-primary );animation:foo}'
	. '@media(max-width:1024px){.elementor-468 .elementor-element.elementor-element-41420ebb .elementor-heading-title{font-size:48px;line-height:52px}}'
	. '@media(max-width:767px){.elementor-468 .elementor-element.elementor-element-41420ebb .elementor-heading-title{font-size:36px;line-height:40px}}';
$kit_css = '.elementor-kit-305{--e-global-color-primary:#000000;--e-global-color-accent:#FF5959}';
$css = $hero::compile( $page_css, $kit_css, 468, 305, $ids );
$assert( false !== strpos( $css, '.elementor-kit-305{--e-global-color-primary:#000000;}' ), 'Debe incluir sólo la variable global usada.' );
$assert( false !== strpos( $css, '@media (max-width:1024px)' ) && false !== strpos( $css, '@media (max-width:767px)' ), 'Debe conservar reglas responsive.' );
$assert( false === strpos( $css, 'animation:' ) && false === strpos( $css, '--overflow:' ) && false === strpos( $css, '--e-global-color-accent' ), 'No debe copiar CSS no crítico.' );
$assert( '' === $hero::compile( $page_css, '', 468, 305, $ids ), 'Sin variable global resuelta no debe inyectar CSS.' );
$ambiguous = $elements;
$ambiguous[] = array( 'id' => 'bbbbb', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'header_size' => 'h1' ) );
$assert( array() === $hero::detect( $ambiguous ), 'Dos H1 son ambiguos.' );
$options = array( 'digitalisimo_performance_hero_generation' => 2, 'digitalisimo_performance_hero_468' => array( 'schema' => 1, 'blog_id' => 7, 'generation' => 2, 'modified' => '2026-10-02 10:00:00', 'safe' => true, 'css' => $css ) );
ob_start(); $hero::print_css(); $html = ob_get_clean();
$assert( false !== strpos( $html, 'id="digitalisimo-critical-hero"' ) && false !== strpos( $html, 'font-size:72px' ), 'Debe imprimir el manifiesto persistente.' );
$options['digitalisimo_performance_hero_generation'] = 3;
ob_start(); $hero::print_css(); $html = ob_get_clean();
$assert( '' === $html, 'Un manifiesto desactualizado no debe imprimirse.' );
$options['digitalisimo_performance_hero_generation'] = 2;
$blog_id = 8;
ob_start(); $hero::print_css(); $html = ob_get_clean();
$assert( '' === $html, 'El manifiesto de otro sitio de la red no debe imprimirse.' );
$blog_id = 7;
$enabled = false;
ob_start(); $hero::print_css(); $html = ob_get_clean();
$assert( '' === $html, 'La opción desactivada debe impedir el CSS.' );
echo "CSS crítico Hero: detección, CSS responsive, variables, caché y exclusión segura correctos.\n";
