<?php
/** Clasifica Custom Code sin ejecutarlo ni borrarlo. */
define( 'ABSPATH', __DIR__ );
function esc_url_raw( $url ) { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : ''; }
function absint( $value ) { return abs( (int) $value ); }
class Digitalisimo_Integrations_Performance_Tracking { public static function sanitize_id( $id, $kind ) { return preg_match( '/^' . strtoupper( $kind === 'ga4' ? 'G' : $kind ) . '-[A-Z0-9]{4,30}$/', $id ) ? $id : ''; } }
class Digitalisimo_Integrations_Performance_Preloads { public static function sanitize_paths( $value ) { return $value; } }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-css.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-migration.php';
$class = Digitalisimo_Integrations_Performance_Migration::class;
$google = $class::classify( "<script>gtag('config','GT-ABC123');</script><script src='https://www.googletagmanager.com/gtag/js?id=G-XYZ123'></script>" );
if ( array( 'GT-ABC123', 'G-XYZ123' ) !== $google['tracking_ids'] || ! $google['gtag'] || $google['gtm'] ) throw new RuntimeException( 'Debe identificar GT y GA4 sin confundirlos con GTM.' );
$gtm = $class::classify( '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-ABC123"></script><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-ABC123"></iframe></noscript>' );
if ( array( 'GTM-ABC123' ) !== $gtm['tracking_ids'] || ! $gtm['gtm'] ) throw new RuntimeException( 'Debe detectar GTM y su noscript.' );
$preload = $class::classify( '<link href="https://example.test/font.woff2" rel="preload" as="font"><style>.elementor-image-carousel-caption{font-style:normal}</style>' );
if ( array( 'https://example.test/font.woff2' ) !== $preload['preloads'] || ! $preload['technical_css'] ) throw new RuntimeException( 'Debe identificar preloads y CSS técnico.' );
$pure_css = $class::classify( '<style>.elementor-image-carousel-caption{font-style:normal!important}</style>' );
if ( '.elementor-image-carousel-caption{font-style:normal!important}' !== $pure_css['technical_css_value'] ) throw new RuntimeException( 'Sólo un bloque CSS aislado debe poder prepararse.' );
if ( '' !== $preload['technical_css_value'] || '' !== $class::pure_css( '<style>.x{color:red}</style><script>alert(1)</script>' ) || '' !== $class::pure_css( '<style>.x{background:url(https://evil.test/x)}</style>' ) ) throw new RuntimeException( 'El HTML mixto o CSS con solicitudes externas no debe importarse.' );
if ( $class::classify( '<p>Contenido normal</p>' )['tracking_ids'] ) throw new RuntimeException( 'Contenido normal no debe crear IDs de tracking.' );
$font = 'https://example.test/uploads/elementor/google-fonts/fonts/a.woff2';
$proposal = $class::proposal( array( 'rows' => array(
	array( 'status' => 'publish', 'tracking_ids' => array( 'GT-ABC123', 'G-XYZ123' ), 'preloads' => array( $font, 'https://evil.test/a.woff2' ) ),
	array( 'status' => 'draft', 'tracking_ids' => array( 'GTM-OTHER9' ), 'preloads' => array() ),
) ), array( 'uploads_baseurl' => 'https://example.test/uploads/', 'faces' => array( array( 'url' => $font ) ) ) );
if ( 'GT-ABC123' !== $proposal['perf_gt_id'] || 'G-XYZ123' !== $proposal['perf_ga4_id'] || 'elementor/google-fonts/fonts/a.woff2' !== $proposal['perf_preload_paths'] || isset( $proposal['perf_gtm_id'] ) ) throw new RuntimeException( 'La propuesta sólo admite IDs inequívocos publicados y WOFF2 inventariados del sitio.' );
$css_proposal = $class::proposal( array( 'rows' => array( array( 'status' => 'publish', 'technical_css_value' => $pure_css['technical_css_value'] ) ) ), array() );
if ( $pure_css['technical_css_value'] !== ( $css_proposal['perf_custom_css'] ?? '' ) ) throw new RuntimeException( 'CSS aislado y válido debe proponerse aunque no exista inventario de fuentes.' );
$ambiguous_css = $class::proposal( array( 'rows' => array( array( 'status' => 'publish', 'technical_css_value' => '.a{color:red}' ), array( 'status' => 'publish', 'technical_css_value' => '.b{color:blue}' ) ) ), array() );
if ( isset( $ambiguous_css['perf_custom_css'] ) ) throw new RuntimeException( 'Dos fragmentos CSS diferentes requieren revisión manual.' );
echo "Migración: Custom Code clasificado sin modificarlo.\n";
