<?php
/** Inventario de Kit y CSS local sin solicitudes externas ni mutaciones de Elementor. */
define( 'ABSPATH', __DIR__ );
define( 'KB_IN_BYTES', 1024 );
define( 'WP_CONTENT_DIR', __DIR__ . '/fixtures/wp-content' );
class Digitalisimo_Integrations_Performance_Cache { public static $last; public static function set( $name, $value ) { self::$last = array( $name, $value ); } }
function absint( $value ) { return abs( (int) $value ); }
function wp_upload_dir() { return array( 'basedir' => WP_CONTENT_DIR . '/uploads', 'baseurl' => 'https://example.test/wp-content/uploads' ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function current_time() { return '2026-10-01 00:00:00'; }
function update_option( $name, $value ) { global $saved; $saved = array( $name, $value ); }
function get_post_types( $args, $output ) { return array( 'page' => 'page' ); }
function get_posts( $args ) { return array( 11, 12 ); }
function get_post_meta( $id, $key, $single ) {
	if ( '_elementor_data' === $key ) return 11 === $id ? '[{"settings":{"title_typography_font_family":"Inter","title_typography_font_weight":"700"}}]' : '[{"settings":{"text_typography_font_family":"Source Serif 4"}}]';
	return array();
}
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-fonts.php';
$class = Digitalisimo_Integrations_Performance_Fonts::class;
$families = $class::families_from_settings( array( 'body_typography_font_family' => 'Inter', 'body_typography_font_weight' => '400', 'body_typography_font_style' => 'normal', 'system_typography' => array( array( 'typography_font_family' => 'Source Serif 4', 'typography_font_weight' => '700' ) ) ) );
if ( 2 !== count( $families ) || 'Inter' !== $families[0]['family'] || array( '400' ) !== array_map( 'strval', $families[0]['weights'] ) || 'Source Serif 4' !== $families[1]['family'] ) throw new RuntimeException( 'Debe leer fuentes del cuerpo y tipografías globales del Kit.' );
$merged = $class::merge_families( array( $families, array( array( 'family' => 'Inter', 'weights' => array(), 'styles' => array() ) ) ) );
if ( $merged[0]['weights'] || $merged[0]['styles'] ) throw new RuntimeException( 'Una variante no especificada debe conservar todos sus pesos y estilos.' );
list( $used, $documents, $complete ) = $class::content_families();
if ( 2 !== $documents || ! $complete || 2 !== count( $used ) || 'Inter' !== $used[0]['family'] ) throw new RuntimeException( 'Debe incluir familias de documentos Elementor publicados.' );
$faces = ( new ReflectionClass( $class ) )->getMethod( 'local_faces' )->invoke( null );
if ( 2 !== count( $faces ) || 'Inter' !== $faces[0]['family'] || '400' !== $faces[0]['weight'] || 'italic' !== $faces[1]['style'] || 'https://example.test/wp-content/uploads/elementor/google-fonts/fonts/inter-regular.woff2' !== $faces[0]['url'] ) throw new RuntimeException( 'Debe leer variantes y WOFF2 locales del sitio.' );
$report = $class::scan();
if ( 0 !== $report['kit_id'] || 2 !== count( $report['faces'] ) || 'fonts' !== Digitalisimo_Integrations_Performance_Cache::$last[0] ) throw new RuntimeException( 'Sin Elementor, el análisis debe ser seguro y guardar sólo datos locales.' );
echo "Fuentes: Kit y CSS local inventariados sin alterar Elementor.\n";
