<?php
/**
 * Slider de dispositivos (`digitalisimo-device-slider`): marcado del dispositivo rediseñado (2026-11-27).
 * - Estructura: dispositivo › marco › pantalla › pista; las flechas son hijas del marco y nunca están dentro de la
 *   pantalla; los puntos van debajo, fuera del dispositivo.
 * - Cada dispositivo pinta sólo sus piezas (isla del celular, cámara de tableta y laptop, base de la laptop, pie del
 *   monitor, barra del navegador); color del marco negro por defecto, plata o blanco; orientación horizontal sólo en
 *   celular y tableta; un valor desconocido cae en lo de siempre (monitor, negro, flechas).
 * - Navegación: flechas, puntos, ambas o ninguna, con `aria-label`, la primera flecha desactivada y el punto actual con
 *   `aria-current`; con una sola captura no hay navegación.
 * - Los widgets ya guardados (sin los ajustes nuevos) se pintan con el diseño nuevo; las clases nuevas no llevan
 *   «elementor» y el editor usa las mismas clases.
 * - La hoja: la captura llena la pantalla (`object-fit: cover`, sin `contain`) y las flechas no se colocan dentro.
 * Uso: php tests/elements-device-slider.php
 */
namespace {
	require __DIR__ . '/fixtures/device-slider-harness.php';

	$checks = 0;
	function check_ds( $ok, $message ) {
		global $checks;
		if ( ! $ok ) { throw new \RuntimeException( $message ); }
		$checks++;
	}
	function ds_dom( $html ) {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();
		return new \DOMXPath( $doc );
	}
	function ds_q( \DOMXPath $x, $class, $context = null ) {
		return $x->query( './/*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]', $context ?: $x->document->documentElement );
	}
	function ds_inside( \DOMNode $node, \DOMNode $ancestor ) {
		for ( $n = $node->parentNode; $n; $n = $n->parentNode ) { if ( $n->isSameNode( $ancestor ) ) { return true; } }
		return false;
	}

	$images = array( array( 'url' => 'https://site.test/a.webp' ), array( 'url' => 'https://site.test/b.webp' ), array( 'url' => 'https://site.test/c.webp' ) );

	// --- Estructura con los valores por defecto (como un widget ya guardado: sin ajustes nuevos) ---------------------
	$html = ds_render( array( 'gallery_images' => $images ) );
	$x    = ds_dom( $html );
	$root = ds_q( $x, 'digi-device-slider' )->item( 0 );
	check_ds( $root && 'digi-device-slider digi-device-slider--desktop digi-device-slider--frame-black digi-device-slider--arrows' === $root->getAttribute( 'class' ), 'Un widget guardado sin ajustes nuevos es un monitor negro con flechas: ' . ( $root ? $root->getAttribute( 'class' ) : 'sin raíz' ) );
	$device = ds_q( $x, 'digi-device-slider__device' )->item( 0 );
	$frame  = ds_q( $x, 'digi-device-slider__frame' )->item( 0 );
	$screen = ds_q( $x, 'digi-device-slider__screen' )->item( 0 );
	$track  = $x->query( '//*[@data-digi-carousel-track]' )->item( 0 );
	check_ds( $device && $frame && $screen && $track && ds_inside( $frame, $device ) && ds_inside( $screen, $frame ) && ds_inside( $track, $screen ), 'Dispositivo › marco › pantalla › pista.' );
	check_ds( 3 === ds_q( $x, 'digi-device-slider__slide', $screen )->length && 3 === $x->query( './/img[contains(@class,"digi-device-slider__image")]', $screen )->length, 'Las tres capturas van dentro de la pantalla.' );
	$arrows = ds_q( $x, 'digi-device-slider__arrow' );
	check_ds( 2 === $arrows->length, 'Dos flechas.' );
	foreach ( $arrows as $arrow ) {
		check_ds( 'button' === $arrow->nodeName && 'button' === $arrow->getAttribute( 'type' ) && $arrow->parentNode->isSameNode( $frame ) && ! ds_inside( $arrow, $screen ), 'Cada flecha es un botón hijo del marco, fuera de la pantalla.' );
		check_ds( 1 === $x->query( './/*[local-name()="svg" and @aria-hidden="true" and @focusable="false"]', $arrow )->length && '' === trim( $arrow->textContent ), 'La flecha es un icono SVG oculto a los lectores, sin texto suelto.' );
	}
	check_ds( 'Captura anterior' === $arrows->item( 0 )->getAttribute( 'aria-label' ) && $arrows->item( 0 )->hasAttribute( 'data-digi-carousel-prev' ) && $arrows->item( 0 )->hasAttribute( 'disabled' ) && 'true' === $arrows->item( 0 )->getAttribute( 'aria-disabled' ), 'La primera flecha: «Captura anterior», del motor y desactivada al empezar.' );
	check_ds( 'Captura siguiente' === $arrows->item( 1 )->getAttribute( 'aria-label' ) && $arrows->item( 1 )->hasAttribute( 'data-digi-carousel-next' ) && ! $arrows->item( 1 )->hasAttribute( 'disabled' ), 'La segunda flecha: «Captura siguiente», activa.' );
	check_ds( 0 === $x->query( '//button', $screen )->length && 0 === ds_q( $x, 'digi-carousel__controls' )->length, 'Ningún botón dentro de la pantalla ni la barra de controles de antes.' );
	$section = $x->query( '//section' )->item( 0 );
	check_ds( $section && 'region' === $section->getAttribute( 'role' ) && 'carrusel' === $section->getAttribute( 'aria-roledescription' ) && 'Capturas de dispositivo' === $section->getAttribute( 'aria-label' ) && $section->hasAttribute( 'data-digi-carousel-drag' ) && ! $section->hasAttribute( 'data-digi-carousel-dots' ), 'Región con nombre y arrastre; sin puntos.' );
	check_ds( '0' === $track->getAttribute( 'tabindex' ), 'La pista se puede enfocar y mover con el teclado.' );
	check_ds( 1 === ds_q( $x, 'digi-device-slider__stand' )->length && $device->lastChild->getAttribute( 'class' ) === 'digi-device-slider__stand', 'El monitor tiene pie, debajo del marco.' );
	$nav = ds_q( $x, 'digi-device-slider__nav' )->item( 0 );
	check_ds( $nav && ! ds_inside( $nav, $device ) && 0 === ds_q( $x, 'digi-device-slider__dot' )->length, 'Con sólo flechas, la fila de navegación (para pantallas estrechas) va fuera del dispositivo y sin puntos.' );
	check_ds( str_contains( $html, 'draggable="false"' ) && str_contains( $html, 'class="digi-device-slider__image"' ), 'La captura no se arrastra como archivo.' );

	// --- Clases nuevas sin «elementor» (sólo la de la fila del repetidor, que Elementor necesita) --------------------
	$all = ds_render( array( 'device_type' => 'mobile', 'navigation' => 'arrows_dots', 'slides' => array( array( '_id' => 'r1', 'background' => 'color', 'title' => 'Uno' ), array( '_id' => 'r2', 'background' => 'color', 'title' => 'Dos' ) ) ) );
	preg_match_all( '/class="([^"]*)"/', $all, $m );
	$tokens = array_unique( preg_split( '/\s+/', implode( ' ', $m[1] ) ) );
	check_ds( ! preg_grep( '/elementor/i', array_filter( $tokens, function ( $t ) { return 0 !== strpos( $t, 'elementor-repeater-item-' ); } ) ), 'Ninguna clase nueva lleva «elementor».' );
	check_ds( ! preg_match( '/elementor/i', strip_tags( $all ) ) && ! preg_match( '/aria-label="[^"]*elementor/i', $all ), 'Ningún texto visible ni nombre accesible lleva «elementor».' );

	// --- Cada dispositivo pinta sus piezas -----------------------------------------------------------------------------
	$parts = array( 'digi-device-slider__island', 'digi-device-slider__camera', 'digi-device-slider__bar', 'digi-device-slider__base', 'digi-device-slider__stand' );
	$expect = array(
		'mobile'  => array( 'digi-device-slider__island' ),
		'tablet'  => array( 'digi-device-slider__camera' ),
		'laptop'  => array( 'digi-device-slider__camera', 'digi-device-slider__base' ),
		'desktop' => array( 'digi-device-slider__stand' ),
		'browser' => array( 'digi-device-slider__bar' ),
		'raro'    => array( 'digi-device-slider__stand' ),
	);
	foreach ( $expect as $type => $want ) {
		$x = ds_dom( ds_render( array( 'device_type' => $type, 'gallery_images' => $images ) ) );
		$found = array_values( array_filter( $parts, function ( $p ) use ( $x ) { return ds_q( $x, $p )->length > 0; } ) );
		check_ds( $want === $found, $type . ': piezas ' . implode( ', ', $found ) );
		$class = ds_q( $x, 'digi-device-slider' )->item( 0 )->getAttribute( 'class' );
		check_ds( str_contains( $class, 'digi-device-slider--' . ( 'raro' === $type ? 'desktop' : $type ) . ' ' ), $type . ': clase del dispositivo.' );
		check_ds( ( 'mobile' === $type || 'tablet' === $type ) === str_contains( $class, 'digi-device-slider--keys' ), $type . ': botones laterales sólo en celular y tableta.' );
		foreach ( ds_q( $x, 'digi-device-slider__island' ) as $island ) { check_ds( ds_inside( $island, ds_q( $x, 'digi-device-slider__screen' )->item( 0 ) ) && 'true' === $island->getAttribute( 'aria-hidden' ), 'La isla va sobre la pantalla y es decorativa.' ); }
		foreach ( array( 'digi-device-slider__camera', 'digi-device-slider__bar' ) as $piece ) {
			foreach ( ds_q( $x, $piece ) as $node ) { check_ds( $node->parentNode->isSameNode( ds_q( $x, 'digi-device-slider__frame' )->item( 0 ) ) && 'true' === $node->getAttribute( 'aria-hidden' ), $type . ': ' . $piece . ' en el marco, decorativa.' ); }
		}
	}

	// --- Color del marco, orientación, isla, botones y encuadre --------------------------------------------------------
	foreach ( array( 'black' => 'black', 'silver' => 'silver', 'white' => 'white', 'rojo' => 'black', '' => 'black' ) as $value => $class ) {
		check_ds( str_contains( ds_render( array( 'frame_style' => $value, 'gallery_images' => $images ) ), 'digi-device-slider--frame-' . $class . ' ' ), 'Color del marco ' . ( $value ?: 'vacío' ) . ' → ' . $class );
	}
	check_ds( str_contains( ds_render( array( 'device_type' => 'mobile', 'orientation' => 'landscape', 'gallery_images' => $images ) ), 'digi-device-slider--landscape' ) && str_contains( ds_render( array( 'device_type' => 'tablet', 'orientation' => 'landscape', 'gallery_images' => $images ) ), 'digi-device-slider--landscape' ), 'Celular y tableta pueden ir en horizontal.' );
	check_ds( ! str_contains( ds_render( array( 'device_type' => 'laptop', 'orientation' => 'landscape', 'gallery_images' => $images ) ), '--landscape' ), 'La laptop no rota.' );
	$plain = ds_render( array( 'device_type' => 'mobile', 'show_notch' => '', 'show_buttons' => '', 'image_position' => 'center', 'gallery_images' => $images ) );
	check_ds( ! str_contains( $plain, 'digi-device-slider__island' ) && ! str_contains( $plain, '--keys' ) && str_contains( $plain, 'digi-device-slider--fit-center' ), 'Sin isla, sin botones laterales y captura centrada cuando se pide.' );

	// --- Navegación -------------------------------------------------------------------------------------------------------
	$x = ds_dom( ds_render( array( 'navigation' => 'dots', 'gallery_images' => $images ) ) );
	$dots = ds_q( $x, 'digi-device-slider__dot' );
	check_ds( 3 === $dots->length && 0 === ds_q( $x, 'digi-device-slider__arrow' )->length && $x->query( '//section[@data-digi-carousel-dots]' )->length, 'Puntos: uno por captura, sin flechas.' );
	foreach ( $dots as $i => $dot ) {
		check_ds( 'button' === $dot->nodeName && (string) $i === $dot->getAttribute( 'data-digi-carousel-dot' ) && sprintf( 'Captura %d de 3', $i + 1 ) === $dot->getAttribute( 'aria-label' ) && ( 0 === $i ) === ( 'true' === $dot->getAttribute( 'aria-current' ) ) && ! ds_inside( $dot, ds_q( $x, 'digi-device-slider__device' )->item( 0 ) ), 'Punto ' . ( $i + 1 ) . ': botón con nombre, fuera del dispositivo.' );
	}
	check_ds( str_contains( ds_q( $x, 'digi-device-slider' )->item( 0 )->getAttribute( 'class' ), '--dots' ) && ! str_contains( ds_q( $x, 'digi-device-slider' )->item( 0 )->getAttribute( 'class' ), '--arrows' ), 'Clase de puntos sin la de flechas (sin espacio lateral).' );
	$both = ds_render( array( 'navigation' => 'arrows_dots', 'gallery_images' => $images ) );
	check_ds( 2 === substr_count( $both, 'digi-device-slider__arrow ' ) && 3 === substr_count( $both, 'class="digi-device-slider__dot"' ) && str_contains( $both, 'digi-device-slider--arrows digi-device-slider--dots' ), 'Flechas y puntos.' );
	$none = ds_render( array( 'navigation' => 'none', 'gallery_images' => $images ) );
	check_ds( ! str_contains( $none, '<button' ) && ! str_contains( $none, 'digi-device-slider__nav' ) && ! str_contains( $none, '--arrows' ), 'Sin navegación: ni botones ni fila.' );
	$one = ds_render( array( 'navigation' => 'arrows_dots', 'gallery_images' => array( $images[0] ) ) );
	check_ds( ! str_contains( $one, '<button' ) && ! str_contains( $one, 'data-digi-carousel-drag' ) && str_contains( $one, 'digi-device-slider__screen' ), 'Una sola captura: la pantalla sin navegación ni arrastre.' );
	check_ds( '' === ds_render( array( 'gallery_images' => array() ) ), 'Sin capturas no se pinta nada.' );

	// --- Controles -----------------------------------------------------------------------------------------------------------
	$w = ds_widget();
	check_ds( array( 'mobile', 'tablet', 'laptop', 'desktop', 'browser' ) === array_keys( $w->controls['device_type']['options'] ) && 'desktop' === $w->controls['device_type']['default'], 'Celular, tableta, laptop, monitor y navegador; el valor por defecto de siempre.' );
	check_ds( array( 'black', 'silver', 'white' ) === array_keys( $w->controls['frame_style']['options'] ) && 'black' === $w->controls['frame_style']['default'], 'Marco negro, plata o blanco; negro por defecto.' );
	check_ds( array( 'arrows', 'dots', 'arrows_dots', 'none' ) === array_keys( $w->controls['navigation']['options'] ) && 'arrows' === $w->controls['navigation']['default'], 'Navegación con los valores de Element Pack.' );
	check_ds( array( '{{WRAPPER}} .digi-device-slider' => 'max-width:{{SIZE}}px;' ) === $w->controls['width']['selectors'], 'El ancho máximo sigue escribiendo en el mismo selector (CSS ya generado).' );
	foreach ( array( 'frame_color' => '--digi-ds-frame', 'arrows_color' => '--digi-ds-arrow-bg', 'arrows_hover_color' => '--digi-ds-arrow-bg-hover', 'device_buttons_color' => '--digi-ds-buttons', 'arrows_size' => '--digi-ds-arrow' ) as $name => $var ) {
		check_ds( str_starts_with( (string) current( $w->controls[ $name ]['selectors'] ), $var . ':' ), $name . ' escribe ' . $var . ' (mismo nombre que el control de Element Pack: el valor guardado se aplica).' );
	}

	// --- Editor: mismas piezas y clases --------------------------------------------------------------------------------------
	ob_start(); ( digi_test_accessible( new \ReflectionMethod( $w, 'content_template' ) ) )->invoke( $w ); $editor = ob_get_clean();
	foreach ( array( 'digi-device-slider__device', 'digi-device-slider__frame', 'digi-device-slider__screen', 'digi-device-slider__island', 'digi-device-slider__camera', 'digi-device-slider__bar', 'digi-device-slider__base', 'digi-device-slider__stand', 'digi-device-slider__arrow--prev', 'digi-device-slider__dot', 'data-digi-carousel-dots', 'data-digi-carousel-drag', 'digi-device-slider--frame-', 'digi-device-slider--landscape', 'aria-label="Captura anterior"', "'laptop'" ) as $needle ) {
		check_ds( str_contains( $editor, $needle ), 'El editor pinta ' . $needle );
	}

	// --- Hoja de estilos -----------------------------------------------------------------------------------------------------
	$css = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/device-slider.css' );
	check_ds( (bool) preg_match( '/img\.digi-device-slider__image\{[^}]*object-fit:cover/', $css ) && ! str_contains( $css, 'object-fit:contain' ), 'La captura llena la pantalla (cover), sin contain.' );
	check_ds( ! str_contains( $css, 'digi-carousel__controls' ) && ! preg_match( '/__screen[^{]*__arrow/', $css ), 'Ninguna regla pone las flechas dentro de la pantalla.' );
	check_ds( str_contains( $css, 'scrollbar-width:none' ) && str_contains( $css, 'prefers-reduced-motion:reduce' ) && str_contains( $css, ':focus-visible' ), 'Sin barra de desplazamiento, con movimiento reducido y foco visible.' );
	check_ds( ! preg_match( '/elementor/i', $css ), 'La hoja no nombra clases de Elementor.' );

	echo "DIGITALÍSIMO Elements: Slider de dispositivos (marco, pantalla, flechas fuera, puntos, dispositivos y compatibilidad): {$checks} comprobaciones.\n";
}
