<?php
/** Firma comparable de un HTML pintado: textos visibles, enlaces (con destino) e imágenes (archivo y ALT). */
function digi_ab_signature( $html ) {
	$sig = array( 'text' => array(), 'links' => array(), 'images' => array() );
	if ( '' === trim( $html ) ) {
		return $sig;
	}
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	$xpath = new DOMXPath( $doc );
	foreach ( $xpath->query( '//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::svg) and not(ancestor::noscript) and not(ancestor::template)]' ) as $node ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $node->nodeValue ) );
		// Los tiempos relativos («5 seconds ago») cambian entre fases sin que cambie el contenido.
		$text = preg_replace( '/\b\d+ (second|minute)s? ago\b/', 'N ago', $text );
		if ( '' !== $text ) {
			$sig['text'][] = $text;
		}
	}
	foreach ( $xpath->query( '//a[@href]' ) as $a ) {
		$sig['links'][] = $a->getAttribute( 'href' ) . ( $a->getAttribute( 'target' ) ? ' →' . $a->getAttribute( 'target' ) : '' );
	}
	foreach ( $xpath->query( '//img' ) as $img ) {
		$src = $img->getAttribute( 'src' ) ?: $img->getAttribute( 'data-src' );
		$sig['images'][] = basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ) . ' | alt=' . trim( $img->getAttribute( 'alt' ) );
	}
	return $sig;
}
