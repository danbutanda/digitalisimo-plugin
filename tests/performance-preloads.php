<?php
/** Asegura precarga contextual y sin duplicados, sin iniciar WordPress completo. */
define( 'ABSPATH', __DIR__ );
function absint( $value ) { return abs( (int) $value ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_upload_dir() { return array( 'baseurl' => 'https://site.test/wp-content/uploads' ); }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-preloads.php';
$class = Digitalisimo_Integrations_Performance_Preloads::class;
if ( 'off' !== $class::sanitize_mode( 'malicioso' ) || 2 !== $class::sanitize_limit( 90 ) || 1 !== $class::sanitize_limit( 0 ) ) throw new RuntimeException( 'El modo y el límite deben cerrarse a valores seguros.' );
if ( "elementor/google-fonts/fonts/a.woff2" !== $class::sanitize_paths( "../fuera.woff2\nelementor/google-fonts/fonts/a.woff2\nelementor/google-fonts/fonts/a.woff2\nhttps://evil.test/a.woff2" ) ) throw new RuntimeException( 'Sólo rutas locales válidas y únicas.' );
$report = array( 'uploads_baseurl' => 'https://site.test/wp-content/uploads/', 'families' => array( array( 'family' => 'Inter' ) ), 'faces' => array(
	array( 'family' => 'Inter', 'weight' => '400', 'style' => 'normal', 'css' => 'inter.css', 'url' => 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/a.woff2' ),
	array( 'family' => 'Inter', 'weight' => '400', 'style' => 'normal', 'css' => 'inter.css', 'url' => 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/a.woff2' ),
	array( 'family' => 'Inter', 'weight' => '400', 'style' => 'italic', 'css' => 'inter.css', 'url' => 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/b.woff2' ),
	array( 'family' => 'Other', 'weight' => '400', 'style' => 'normal', 'css' => 'other.css', 'url' => 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/c.woff2' ),
) );
$queued = array( 'inter.css' => true );
$auto = $class::candidates( $report, 'auto', '', $queued, 2, array() );
if ( array( 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/a.woff2' ) !== $auto ) throw new RuntimeException( 'Auto sólo debe elegir la fuente normal del Kit y evitar duplicados.' );
$manual = $class::candidates( $report, 'manual', "elementor/google-fonts/fonts/b.woff2\nelementor/google-fonts/fonts/c.woff2", $queued, 2, array() );
if ( array( 'https://site.test/wp-content/uploads/elementor/google-fonts/fonts/b.woff2' ) !== $manual ) throw new RuntimeException( 'Manual debe exigir CSS encolado.' );
$blocked = $class::candidates( $report, 'manual', 'elementor/google-fonts/fonts/b.woff2', $queued, 2, $manual );
if ( $blocked ) throw new RuntimeException( 'No se debe duplicar un preload publicado.' );
echo "Preloads: rutas, contexto, límite y duplicados correctos.\n";
