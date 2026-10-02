<?php
/**
 * Reglas de la auditoría de calidad: enlaces, nombres accesibles, encabezados,
 * alt, objetivos táctiles, contraste, imágenes medidas, CSS por página y resumen.
 * Sin WordPress: las reglas son funciones puras sobre datos medidos.
 */
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-quality-rules.php';
$r = Digitalisimo_Integrations_Quality_Rules::class;
function check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function statuses( $rows ) { return array_map( function( $row ) { return $row['status']; }, $rows ); }
$link = function( $href, $extra = array() ) { return $extra + array( 'href' => $href, 'resolved' => null === $href ? '' : ( preg_match( '~^https?://~', (string) $href ) ? $href : 'https://example.test/' . ltrim( (string) $href, '/' ) ), 'name' => 'Contacto', 'visible' => true, 'selector' => 'a.x', 'widget' => 'button' ); };

// Destinos: ausente, vacío, «#», javascript:, variables sin resolver y protocolos sin dato.
check( 'ERROR' === $r::classify_href( $link( null ) )[0], 'Un <a> sin href es un error.' );
check( 'ERROR' === $r::classify_href( $link( '' ) )[0] && 'ERROR' === $r::classify_href( $link( '  ' ) )[0], 'Un href vacío es un error.' );
check( 'ERROR' === $r::classify_href( $link( '#' ) )[0], 'href="#" sin submenú es un error.' );
check( 'ADVERTENCIA' === $r::classify_href( $link( '#', array( 'submenu' => true ) ) )[0], 'href="#" que abre un submenú es una advertencia.' );
check( 'ERROR' === $r::classify_href( $link( 'javascript:void(0)' ) )[0], 'javascript: no es rastreable.' );
check( 'ERROR' === $r::classify_href( $link( 'https://example.test/{{url}}' ) )[0], 'Una variable sin resolver es un error.' );
check( 'ERROR' === $r::classify_href( $link( 'mailto:' ) )[0] && null === $r::classify_href( $link( 'mailto:hola@example.test?subject=x' ) ), 'mailto exige dirección.' );
check( 'ERROR' === $r::classify_href( $link( 'tel:' ) )[0] && null === $r::classify_href( $link( 'tel:+525512345678' ) ), 'tel exige número.' );
check( null === $r::classify_href( $link( '#elementor-action%3Aaction%3Dpopup' ) ), 'Las acciones de Elementor (popups) no se reportan.' );
check( null === $r::classify_href( $link( '#contenido', array( 'target_exists' => true ) ) ) && 'ADVERTENCIA' === $r::classify_href( $link( '#nada', array( 'target_exists' => false ) ) )[0], 'Un ancla sólo se reporta si su destino no existe.' );
check( null === $r::classify_href( $link( '/servicios/' ) ) && null === $r::classify_href( $link( 'https://wa.me/5215512345678' ) ), 'Rutas relativas y URLs absolutas válidas no se reportan.' );
check( 'ERROR' === $r::classify_href( array( 'href' => 'http://', 'resolved' => 'http://' ) )[0], 'Una URL sin host es inválida.' );
$rows = $r::link_issues( array( $link( '#', array( 'social' => true ) ), $link( '/ok/' ), $link( null, array( 'button' => true ) ) ) );
check( 2 === count( $rows ) && 'Icono social' === $rows[0]['kind'] && 'Botón' === $rows[1]['kind'] && '(ausente)' === $rows[1]['href'], 'Se informan sólo los enlaces con problema, con su tipo.' );

// Nombre accesible.
$rows = $r::name_issues( array( $link( '/a/', array( 'name' => '', 'icon' => true ) ), $link( '/b/', array( 'name' => '' ) ) ) );
check( array( 'ERROR', 'ERROR' ) === statuses( $rows ) && false !== strpos( $rows[0]['issue'], 'aria-label' ), 'Un enlace sin nombre es un error; si es un icono se pide aria-label.' );
$rows = $r::name_issues( array( $link( '/blog/uno/', array( 'name' => 'Leer más' ) ) ) );
check( 1 === count( $rows ) && 'ADVERTENCIA' === $rows[0]['status'], 'Un texto genérico es una advertencia.' );
$rows = $r::name_issues( array( $link( 'https://example.test/contacto/', array( 'name' => 'Contacto' ) ), $link( 'https://example.test/contacto', array( 'name' => 'Ir a contacto' ) ) ) );
check( ! $rows, 'Nombres que se contienen («Contacto» / «Ir a contacto») son compatibles, con o sin barra final.' );
$rows = $r::name_issues( array( $link( 'https://example.test/contacto/', array( 'name' => 'Contacto' ) ), $link( 'https://example.test/contacto/#form', array( 'name' => 'Cotiza ahora' ) ) ) );
check( 1 === count( $rows ) && array( 'Contacto', 'Cotiza ahora' ) === $rows[0]['names'], 'Mismo destino con nombres incompatibles muestra ambos nombres.' );
$rows = $r::name_issues( array( $link( 'https://example.test/a/', array( 'name' => 'Servicios' ) ), $link( 'https://example.test/b/', array( 'name' => 'servicios' ) ) ) );
check( 1 === count( $rows ) && 2 === count( $rows[0]['urls'] ), 'Mismo nombre hacia destinos distintos es ambiguo.' );
check( ! $r::name_issues( array( $link( '/x/', array( 'name' => '', 'visible' => false ) ) ) ), 'Los enlaces ocultos no se evalúan por nombre.' );

// Encabezados.
$h = function( $level, $text = 'Título', $visible = true ) { return array( 'level' => $level, 'text' => $text, 'visible' => $visible, 'selector' => 'h' . $level ); };
check( 'ERROR' === $r::heading_issues( array() )[0]['status'], 'Sin encabezados es un error.' );
$rows = $r::heading_issues( array( $h( 2 ), $h( 3 ) ) );
check( in_array( 'Página sin H1 visible.', array_column( $rows, 'issue' ), true ), 'Falta de H1 detectada.' );
$rows = $r::heading_issues( array( $h( 1 ), $h( 1, 'Otro', false ), $h( 2 ), $h( 4 ), $h( 3 ), $h( 5 ) ) );
$issues = array_column( $rows, 'issue' );
check( 2 === count( array_filter( $issues, function( $i ) { return 0 === strpos( $i, 'Salto' ); } ) ) && in_array( 'Salto H2 → H4: falta un nivel intermedio.', $issues, true ) && in_array( 'Salto H3 → H5: falta un nivel intermedio.', $issues, true ), 'Saltos H2→H4 y H3→H5 detectados.' );
check( ! in_array( 'ADVERTENCIA', array_map( function( $row ) { return false !== strpos( $row['issue'], 'H1 visibles' ) ? 'ADVERTENCIA' : ''; }, $rows ), true ), 'Un H1 oculto (variante de otro dispositivo) no cuenta como H1 múltiple.' );
$rows = $r::heading_issues( array( $h( 1 ), $h( 1, 'Segundo' ), $h( 2, '01' ), $h( 2, '' ), $h( 2, str_repeat( 'texto ', 30 ) ) ) );
$issues = implode( '|', array_column( $rows, 'issue' ) );
check( false !== strpos( $issues, 'Hay 2 H1' ) && false !== strpos( $issues, 'números o símbolos' ) && false !== strpos( $issues, 'Encabezado vacío' ) && false !== strpos( $issues, 'muy largo' ), 'H1 múltiples, uso visual, vacíos y párrafos como título se informan.' );
$tree = $r::heading_tree( array( $h( 1, 'Inicio' ), $h( 2, 'A' ), $h( 3, 'A1' ), $h( 3, 'A2' ), $h( 2, 'B' ), $h( 3, 'B1' ) ) );
check( array( 'H1 Inicio', '├─ H2 A', '│   ├─ H3 A1', '│   └─ H3 A2', '└─ H2 B', '    └─ H3 B1' ) === $tree, 'El árbol dibuja ramas y continuaciones: ' . implode( ' / ', $tree ) );

// Alt.
$img = function( $extra ) { return $extra + array( 'visible' => true, 'rect' => array( 200, 100 ), 'src' => 'x.jpg', 'selector' => 'img' ); };
check( 'ERROR' === $r::alt_issues( array( $img( array( 'alt' => null ) ) ) )[0]['status'], 'Sin alt es un error.' );
check( ! $r::alt_issues( array( $img( array( 'alt' => '' ) ) ) ), 'Alt vacío en una imagen pequeña se respeta como decorativa.' );
check( 'ADVERTENCIA' === $r::alt_issues( array( $img( array( 'alt' => '', 'rect' => array( 800, 400 ) ) ) ) )[0]['status'], 'Alt vacío en una imagen grande pide confirmación.' );
check( 'ERROR' === $r::alt_issues( array( $img( array( 'alt' => '', 'classification' => 'informative' ) ) ) )[0]['status'], 'Una informativa con alt vacío es un error.' );
check( ! $r::alt_issues( array( $img( array( 'alt' => '', 'rect' => array( 800, 400 ), 'classification' => 'decorative' ) ) ) ), 'Una decorativa con alt vacío es correcta.' );
check( false !== strpos( $r::alt_issues( array( $img( array( 'alt' => 'Equipo de trabajo', 'caption' => 'Equipo de trabajo.' ) ) ) )[0]['issue'], 'caption' ), 'Alt igual al caption.' );
check( false !== strpos( $r::alt_issues( array( $img( array( 'alt' => 'Ver servicios', 'link_text' => 'ver servicios' ) ) ) )[0]['issue'], 'enlace' ), 'Alt igual al texto del enlace.' );
check( false !== strpos( $r::alt_issues( array( $img( array( 'alt' => 'IMG_2034.jpg' ) ) ) )[0]['issue'], 'archivo' ) && false !== strpos( $r::alt_issues( array( $img( array( 'alt' => 'banner-home-2024-final' ) ) ) )[0]['issue'], 'archivo' ), 'Alt con forma de nombre de archivo.' );
check( 'ADVERTENCIA' === $r::alt_issues( array( $img( array( 'alt' => 'Logo', 'aria_hidden' => true ) ) ) )[0]['status'], 'Oculta con aria-hidden pero con alt descriptivo.' );
check( ! $r::alt_issues( array( $img( array( 'alt' => null, 'rect' => array( 1, 1 ) ) ), $img( array( 'alt' => null, 'visible' => false ) ), $img( array( 'alt' => 'Fachada de la oficina' ) ) ) ), 'Píxeles, imágenes ocultas y alts correctos no se reportan.' );

// Objetivos táctiles (WCAG 2.5.8).
$t = function( $x, $y, $w, $h, $extra = array() ) { return $extra + array( 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'name' => 'c', 'selector' => 's' ); };
check( ! $r::touch_issues( array( $t( 0, 0, 44, 44 ), $t( 50, 0, 24, 24 ) ) ), 'Controles de 24 px o más cumplen.' );
$rows = $r::touch_issues( array( $t( 0, 0, 8, 8 ), $t( 12, 0, 8, 8 ) ) );
check( array( 'ERROR', 'ERROR' ) === statuses( $rows ), 'Dos bullets de 8 px a 12 px de distancia están demasiado cerca.' );
$rows = $r::touch_issues( array( $t( 0, 0, 16, 16 ), $t( 100, 0, 16, 16 ) ) );
check( array( 'ADVERTENCIA', 'ADVERTENCIA' ) === statuses( $rows ) && 100 === $rows[0]['gap'], 'Pequeños pero separados: advertencia con la distancia.' );
$rows = $r::touch_issues( array( $t( 0, 0, 20, 20 ), $t( 21, 0, 100, 40 ) ) );
check( array( "ADVERTENCIA" ) === statuses( $r::touch_issues( array( $t( 0, 0, 20, 20 ), $t( 25, 0, 100, 40 ) ) ) ), "A 15 px del centro el círculo de 24 px no toca al vecino." );
check( array( 'ERROR' ) === statuses( $rows ), 'Pequeño junto a un control grande a menos de 12 px del centro.' );
check( ! $r::touch_issues( array( $t( 0, 0, 10, 10, array( 'inline' => true ) ), $t( 0, 0, 0, 0 ) ) ), 'Enlaces en línea y controles sin tamaño están exentos.' );
check( 1 === count( $r::touch_issues( array( $t( 0, 0, 40, 40 ), $t( 10, 10, 16, 16 ) ) ) ) && 'ADVERTENCIA' === $r::touch_issues( array( $t( 0, 0, 40, 40 ), $t( 10, 10, 16, 16 ) ) )[0]['status'], 'Un control dentro de otro no cuenta como vecino.' );

// Contraste.
check( abs( $r::contrast_ratio( array( 0, 0, 0 ), array( 255, 255, 255 ) ) - 21 ) < 0.01 && abs( $r::contrast_ratio( array( 119, 119, 119 ), array( 255, 255, 255 ) ) - 4.48 ) < 0.01, 'Ratios de referencia WCAG.' );
check( array( 10.0, 20.0, 30.0, 0.5 ) === $r::parse_color( 'rgba(10, 20, 30, 0.5)' ) && array( 10.0, 20.0, 30.0, 0.25 ) === $r::parse_color( 'rgb(10 20 30 / 25%)' ) && null === $r::parse_color( 'transparent' ), 'Colores computados con y sin comas.' );
$result = $r::contrast_issues( array(
	array( 'fg' => 'rgb(119, 119, 119)', 'bg' => 'rgb(255, 255, 255)', 'size' => 16, 'weight' => 400, 'text' => 'gris' ),
	array( 'fg' => 'rgb(119, 119, 119)', 'bg' => 'rgb(255, 255, 255)', 'size' => 24, 'weight' => 400, 'text' => 'grande' ),
	array( 'fg' => 'rgb(0, 0, 0)', 'bg' => 'rgb(255, 255, 255)', 'size' => 16, 'opacity' => 0.4, 'text' => 'opaco' ),
	array( 'fg' => 'rgb(255, 255, 255)', 'bg' => null, 'size' => 16, 'text' => 'sobre imagen' ),
	array( 'fg' => 'rgba(0, 0, 0, 0)', 'bg' => 'rgb(255, 255, 255)', 'size' => 16, 'text' => 'invisible' ),
) );
check( 2 === count( $result['rows'] ) && 'gris' === $result['rows'][0]['text'] && 4.5 === $result['rows'][0]['expected'] && 'opaco' === $result['rows'][1]['text'] && '#999999' === $result['rows'][1]['color'], 'Texto normal bajo 4.5 y opacidad aplicada; el grande pasa con 3:1.' );
check( 3 === $result['checked'] && 1 === $result['unknown'], 'Fondos con imagen se cuentan como desconocidos, no como errores.' );

// Imágenes medidas: sólo se advierte cuando existe una variante menor suficiente.
$m = array( 'natural' => array( 1920, 1080 ), 'rect' => array( 400, 225 ), 'srcset' => '300w, 768w, 1024w, 1920w' );
$v = $r::image_issue( $m, 2 );
check( 'ADVERTENCIA' === $v['status'] && 800 === $v['needed'] && 1024 === $v['candidate'], 'Variante de 1024 bastaría para 400 px × DPR 2.' );
check( 'OK' === $r::image_issue( array( 'natural' => array( 1024, 576 ) ) + $m, 2 )['status'], 'Si ya se descarga la variante suficiente, está bien.' );
check( 'OK' === $r::image_issue( array( 'srcset' => '1920w' ) + $m, 2 )['status'], 'Sin variante menor suficiente no hay nada que corregir.' );
check( false !== strpos( $r::image_issue( array( 'srcset' => '' ) + $m, 2 )['issue'], 'no hay srcset' ), 'Sin srcset se indica.' );
check( 'OK' === $r::image_issue( array( 'natural' => array( 1920, 1080 ), 'rect' => array( 400, 600 ), 'fit' => 'cover', 'srcset' => '768w, 1024w, 1920w' ), 2 )['status'], 'Con object-fit: cover se usa el lado que recorta.' );
check( null === $r::image_issue( array( 'natural' => array( 0, 0 ), 'rect' => array( 400, 200 ) ), 2 ), 'Una imagen no cargada no se evalúa.' );

// CSS por página.
$css = function( $extra ) { return $extra + array( 'eligible' => true, 'present' => true, 'selected' => false, 'enabled' => true, 'above_desktop' => false, 'above_mobile' => false ); };
check( $r::css_verdict( $css( array() ) )['safe'], 'Un widget debajo del primer viewport en ambas vistas es seguro.' );
check( ! $r::css_verdict( $css( array( 'above_mobile' => true ) ) )['safe'], 'Visible en el primer viewport móvil no es seguro.' );
check( 'ERROR' === $r::css_verdict( $css( array( 'above_desktop' => true, 'selected' => true ) ) )['status'], 'Diferir una hoja del primer viewport es un error.' );
check( 'NO APLICABLE' === $r::css_verdict( $css( array( 'eligible' => false ) ) )['status'] && 'NO APLICABLE' === $r::css_verdict( $css( array( 'present' => false ) ) )['status'], 'Hojas no aptas o ausentes no aplican.' );
check( 'EN LISTA · diferido desactivado' === $r::css_verdict( $css( array( 'selected' => true, 'enabled' => false ) ) )['state'], 'El estado actual refleja el interruptor.' );

// Resumen.
$summary = $r::summarize( array( 'links' => array( array( 'status' => 'ERROR' ) ), 'headings' => array( array( 'status' => 'ADVERTENCIA' ) ), 'measured' => true ), array( 'enabled' => true, 'valid' => true, 'fallback' => true ) );
check( 'ERROR' === $summary['links']['status'] && 'ADVERTENCIA' === $summary['headings']['status'] && 'ERROR' === $summary['seo']['status'] && 'OK' === $summary['a11y']['status'] && 'ADVERTENCIA' === $summary['llms']['status'], 'El resumen agrega por área.' );
$summary = $r::summarize( array(), array( 'enabled' => false ) );
check( 'NO APLICABLE' === $summary['links']['status'] && 'NO APLICABLE' === $summary['llms']['status'], 'Sin medición o sin llms.txt el área no aplica.' );

echo "Calidad: enlaces, nombres, encabezados, alt, táctiles, contraste, imágenes, CSS y resumen correctos.\n";
