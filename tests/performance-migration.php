<?php
/** Clasifica Custom Code sin ejecutarlo ni borrarlo. */
define( 'ABSPATH', __DIR__ );
function esc_url_raw( $url ) { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : ''; }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-migration.php';
$class = Digitalisimo_Integrations_Performance_Migration::class;
$google = $class::classify( "<script>gtag('config','GT-ABC123');</script><script src='https://www.googletagmanager.com/gtag/js?id=G-XYZ123'></script>" );
if ( array( 'GT-ABC123', 'G-XYZ123' ) !== $google['tracking_ids'] || ! $google['gtag'] || $google['gtm'] ) throw new RuntimeException( 'Debe identificar GT y GA4 sin confundirlos con GTM.' );
$gtm = $class::classify( '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-ABC123"></script><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-ABC123"></iframe></noscript>' );
if ( array( 'GTM-ABC123' ) !== $gtm['tracking_ids'] || ! $gtm['gtm'] ) throw new RuntimeException( 'Debe detectar GTM y su noscript.' );
$preload = $class::classify( '<link href="https://example.test/font.woff2" rel="preload" as="font"><style>.elementor-image-carousel-caption{font-style:normal}</style>' );
if ( array( 'https://example.test/font.woff2' ) !== $preload['preloads'] || ! $preload['technical_css'] ) throw new RuntimeException( 'Debe identificar preloads y CSS técnico.' );
if ( $class::classify( '<p>Contenido normal</p>' )['tracking_ids'] ) throw new RuntimeException( 'Contenido normal no debe crear IDs de tracking.' );
echo "Migración: Custom Code clasificado sin modificarlo.\n";
