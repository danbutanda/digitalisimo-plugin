<?php
/** Generador de imágenes de los widgets propios: adjuntos por WordPress, URL con las mismas decisiones de carga, sizes por columnas. */
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return 0 === strpos( (string) $v, 'javascript:' ) ? '' : htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function get_post_meta( $id, $key, $single ) { return 12 === $id ? 'ALT de Medios' : ''; }
	function get_the_title( $id ) { return 'Adjunto ' . $id; }
	function get_post_mime_type( $id ) { return 13 === $id ? 'image/gif' : 'image/webp'; }
	function wp_get_attachment_url( $id ) { return 'https://site.test/media/' . $id . '.webp'; }
	function wp_get_attachment_image_src( $id, $size ) { $GLOBALS['dimension_sizes'][] = $size; return 12 === $id ? array( 'https://site.test/media/12.webp', 'large' === $size ? 1024 : 800, 'large' === $size ? 512 : 400 ) : false; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { $GLOBALS['image_markup_calls'][] = array( $id, $size, $attrs ); if ( 99 === $id ) { return ''; } $out = '<img'; foreach ( $attrs as $k => $v ) { $out .= ' ' . $k . '="' . esc_attr( $v ) . '"'; } return $out . ' src="https://site.test/media/' . $id . '.webp" srcset="https://site.test/media/' . $id . '.webp 800w" width="800" height="400" loading="lazy">'; }
	function wp_get_loading_optimization_attributes( $tag, $attrs, $context ) { $GLOBALS['image_markup_context'] = $context; return isset( $attrs['width'], $attrs['height'] ) ? array( 'loading' => 'lazy', 'decoding' => 'async' ) : array( 'decoding' => 'async' ); }
	function check_image_markup( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-image-markup.php';
	$m = '\\Digitalisimo\\Elements\\Image_Markup';

	// Adjunto: WordPress decide srcset, dimensiones y carga; el ALT viene de Medios y decoding va siempre.
	$html = $m::img( array( 'id' => 12, 'url' => 'https://site.test/media/12.webp' ), array( 'class' => 'digi-x__image', 'alt' => 'Nombre', 'columns' => array( 4, 2, 1 ) ) );
	$call = end( $GLOBALS['image_markup_calls'] );
	check_image_markup( 12 === $call[0] && 'medium' === $call[1] && 'digi-x__image' === $call[2]['class'] && 'ALT de Medios' === $call[2]['alt'] && 'async' === $call[2]['decoding'] && ! isset( $call[2]['loading'] ), 'El adjunto pasa por wp_get_attachment_image con clase, ALT de Medios y decoding, sin forzar loading.' );
	check_image_markup( '(max-width: 767px) 100vw, (max-width: 1024px) 50vw, 285px' === $call[2]['sizes'] && str_contains( $html, 'srcset=' ), 'sizes se calcula por columnas y breakpoint.' );
	check_image_markup( '' === $m::sizes_for_columns( 0 ) && '(max-width: 767px) 100vw, (max-width: 1024px) 100vw, 1140px' === $m::sizes_for_columns( 1 ) && '(max-width: 767px) 50vw, (max-width: 1024px) 34vw, 380px' === $m::sizes_for_columns( 3, 3, 2 ), 'Columnas vacías dejan decidir a WordPress; tableta y móvil heredan hacia abajo.' );
	check_image_markup( array( 3, 2, 0 ) === $m::columns( array( 'columns' => '3', 'columns_tablet' => '2' ), 'columns', 4 ) && array( 4, 0, 0 ) === $m::columns( array(), 'columns', 4 ), 'columns() lee el control responsivo con su default.' );

	// ALT: Medios, respaldo del widget, ALT del control, título del adjunto.
	check_image_markup( 'ALT de Medios' === $m::alt( array( 'id' => 12 ), 'x' ) && 'Nombre' === $m::alt( array( 'id' => 5, 'alt' => 'Control' ), 'Nombre' ) && 'Control' === $m::alt( array( 'id' => 5, 'alt' => '<b>Control</b>' ) ) && '' === $m::alt( array( 'id' => 5 ) ) && 'Adjunto 5' === $m::alt( array( 'id' => 5 ), '', true ) && '' === $m::alt( array( 'url' => 'x' ) ), 'El ALT sigue el orden Medios, respaldo, control y, sólo si se pide, título del adjunto.' );
	$m::img( array( 'id' => 12 ), array( 'alt_exact' => ' Literal ' ) );
	$call = end( $GLOBALS['image_markup_calls'] );
	check_image_markup( 'Literal' === $call[2]['alt'], 'alt_exact no consulta Medios.' );
	check_image_markup( array( 1024, 512 ) === $m::dimensions( array( 'id' => 12 ), 'large' ) && array( 320, 160 ) === $m::dimensions( array( 'url' => 'x', 'width' => 320, 'height' => 160 ) ) && array( 0, 0 ) === $m::dimensions( array( 'id' => 5 ) ), 'Las dimensiones salen del adjunto o del control.' );
	$decorative = $m::img( array( 'id' => 12 ), array( 'decorative' => true ) );
	$call = end( $GLOBALS['image_markup_calls'] );
	check_image_markup( '' === $call[2]['alt'] && 'true' === $call[2]['aria-hidden'], 'Una imagen decorativa lleva ALT vacío y aria-hidden.' );

	// Sólo URL: dimensiones si se conocen, decisiones de carga de WordPress, sin srcset inventado, atributos extra.
	$url = $m::img( array( 'url' => 'https://site.test/x.png?a=1&b=2', 'width' => 320, 'height' => 160, 'alt' => 'Guardado' ), array( 'class' => 'c', 'attrs' => array( 'data-k' => 'v' ) ) );
	check_image_markup( '<img class="c" alt="Guardado" decoding="async" data-k="v" src="https://site.test/x.png?a=1&amp;b=2" width="320" height="160" loading="lazy">' === $url && 'digitalisimo_elements' === $GLOBALS['image_markup_context'], 'La imagen por URL conserva dimensiones y recibe las decisiones de carga de WordPress.' );
	$nodims = $m::img( array( 'url' => 'https://site.test/y.png' ) );
	check_image_markup( '<img alt="" decoding="async" src="https://site.test/y.png">' === $nodims, 'Sin dimensiones WordPress no difiere la imagen y el ALT queda vacío.' );
	check_image_markup( '' === $m::img( array() ) && '' === $m::img( array( 'id' => 0, 'url' => '' ) ) && '' === $m::img( array( 'url' => 'javascript:alert(1)' ) ), 'Sin imagen, o con una URL insegura, no se imprime nada.' );
	check_image_markup( ! str_contains( $m::img( array( 'url' => 'https://site.test/z.png' ), array( 'columns' => array( 3 ) ) ), 'sizes=' ), 'Una imagen por URL, sin srcset, no lleva sizes.' );
	// Adjunto que WordPress no puede pintar (borrado): se usa su URL.
	$fallback = $m::img( array( 'id' => 99, 'url' => 'https://site.test/old.png' ), array( 'alt' => 'Viejo' ) );
	check_image_markup( str_contains( $fallback, 'src="https://site.test/old.png"' ) && str_contains( $fallback, 'alt="Viejo"' ), 'Si el adjunto no se puede pintar, se usa la URL guardada.' );
	// sizes explícito gana a columnas; el módulo de imágenes de SEO respeta un sizes presente.
	$m::img( array( 'id' => 12 ), array( 'sizes' => '200px', 'columns' => array( 4 ) ) );
	$call = end( $GLOBALS['image_markup_calls'] );
	check_image_markup( '200px' === $call[2]['sizes'], 'Un sizes explícito gana a las columnas.' );
	// Preconexión: una vez por host.
	check_image_markup( '<link rel="preconnect" href="https://www.youtube-nocookie.com" crossorigin>' === $m::preconnect( 'https://www.youtube-nocookie.com/embed/x' ) && '' === $m::preconnect( 'https://www.youtube-nocookie.com/embed/y' ) && '' === $m::preconnect( '' ), 'La preconexión se imprime una vez por host.' );
	// GIF: siempre entero (los tamaños reducidos pierden la animación y WordPress no imprime srcset del completo).
	$m::img( array( 'id' => 13 ), array( 'size' => 'medium', 'columns' => array( 3 ) ) );
	$call = end( $GLOBALS['image_markup_calls'] );
	check_image_markup( 13 === $call[0] && 'full' === $call[1], 'Un GIF se pide siempre en tamaño completo, aunque el widget pida otro.' );
	$m::img( array( 'id' => 12 ), array( 'size' => 'large' ) );
	$call = end( $GLOBALS['image_markup_calls'] );
	check_image_markup( 'large' === $call[1] && 'large' === $m::size_for( 12, 'large' ) && 'full' === $m::size_for( 13, 'thumbnail' ) && 'medium' === $m::size_for( 0, 'medium' ), 'Los demás tipos y las imágenes sin adjunto conservan su tamaño.' );
	$GLOBALS['dimension_sizes'] = array();
	$m::dimensions( array( 'id' => 13 ), 'medium' );
	check_image_markup( array( 'full' ) === $GLOBALS['dimension_sizes'], 'Las dimensiones de un GIF son las del archivo completo.' );
	echo "DIGITALÍSIMO Elements: generador de imágenes validado (GIF siempre entero).\n";
}
