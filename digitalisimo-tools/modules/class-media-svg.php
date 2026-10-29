<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

/**
 * SVG más ligeros sin cambiar el dibujo.
 *
 * Al subirse (después de la validación del constructor) y por lotes desde «Optimizar medios», un SVG pierde lo que sólo
 * sirve al programa que lo exportó: comentarios, `<metadata>`, nodos y atributos de Inkscape, Illustrator, Sketch,
 * Figma o Serif, la declaración XML y los espacios entre etiquetas fuera de los textos. Se conservan `title`, `desc`,
 * los identificadores, las clases, los estilos y la accesibilidad. El resultado sólo se aplica si vuelve a analizarse
 * como SVG, tiene exactamente los mismos elementos de dibujo y pesa menos; si no, el archivo queda como estaba.
 */
final class Media_SVG {
	/** Espacios de nombres de los editores: lo que llevan no se pinta. */
	const EDITOR_NAMESPACES = array(
		'http://www.inkscape.org/namespaces/inkscape',
		'http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd',
		'http://www.bohemiancoding.com/sketch/ns',
		'http://www.serif.com/',
		'http://www.figma.com/figma/ns',
		'http://ns.adobe.com/AdobeIllustrator/10.0/',
		'http://ns.adobe.com/AdobeSVGViewerExtensions/3.0/',
		'http://ns.adobe.com/Extensibility/1.0/',
		'http://ns.adobe.com/Flows/1.0/',
		'http://ns.adobe.com/ImageReplacement/1.0/',
		'http://ns.adobe.com/GenericCustomNamespace/1.0/',
		'http://ns.adobe.com/XPath/1.0/',
		'http://ns.adobe.com/SaveForWeb/1.0/',
		'http://ns.adobe.com/Variables/1.0/',
		'http://ns.adobe.com/xap/1.0/',
		'http://purl.org/dc/elements/1.1/',
		'http://creativecommons.org/ns#',
		'http://web.resource.org/cc/',
		'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
	);
	const SVG_NS = 'http://www.w3.org/2000/svg';
	/** Elementos cuyo texto (también los espacios) importa. */
	const TEXT_ELEMENTS = array( 'text', 'tspan', 'textPath', 'style', 'title', 'desc', 'script' );
	/** Tope de lo que se procesa: un SVG mayor suele llevar imágenes incrustadas y no se toca. */
	const LIMIT = 2097152;

	public static function init() {
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'upload' ), 20 );
		add_filter( 'wp_handle_sideload_prefilter', array( __CLASS__, 'upload' ), 20 );
	}

	/** Minimiza el archivo temporal de una subida SVG que nadie rechazó. */
	public static function upload( $file ) {
		if ( ! is_array( $file ) || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || 'svg' !== strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) ) return $file;
		if ( ! apply_filters( 'digitalisimo_tools_svg_minify', true ) ) return $file;
		if ( null !== self::minify_file( (string) $file['tmp_name'] ) && isset( $file['size'] ) ) {
			clearstatcache( true, (string) $file['tmp_name'] );
			$file['size'] = (int) filesize( (string) $file['tmp_name'] );
		}
		return $file;
	}

	/** Bytes ahorrados al minimizar el archivo en su sitio, o null si no se cambió. */
	public static function minify_file( $path, $target = null ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) > self::LIMIT ) return null;
		$svg = (string) file_get_contents( $path );
		$min = self::minify( $svg );
		if ( null === $min ) return null;
		if ( false === file_put_contents( null === $target ? $path : $target, $min ) ) return null;
		return strlen( $svg ) - strlen( $min );
	}

	/**
	 * Versión minimizada de un SVG, o null si no se puede minimizar con seguridad o no gana nada.
	 *
	 * @param string $svg Contenido del archivo.
	 */
	public static function minify( $svg ) {
		$svg = (string) $svg;
		if ( '' === trim( $svg ) || strlen( $svg ) > self::LIMIT || ! class_exists( 'DOMDocument' ) ) return null;
		// Una DOCTYPE con subconjunto interno define entidades (Illustrator): quitarla rompería sus referencias.
		if ( preg_match( '/<!DOCTYPE[^>\[]*\[/i', $svg ) ) return null;
		// Sin declaración XML el archivo se lee como UTF-8: otra codificación declarada no se toca.
		if ( preg_match( '/^\s*<\?xml[^>]*encoding\s*=\s*["\']([^"\']+)["\']/i', $svg, $m ) && ! in_array( strtolower( $m[1] ), array( 'utf-8', 'utf8' ), true ) ) return null;
		$doc = self::load( $svg );
		if ( ! $doc ) return null;
		$before = self::drawing( $doc );
		$xpath  = new \DOMXPath( $doc );
		$remove = array();
		foreach ( $xpath->query( '//comment() | //processing-instruction()' ) as $node ) $remove[] = $node;
		foreach ( $xpath->query( '//*' ) as $element ) {
			if ( in_array( $element->namespaceURI, self::EDITOR_NAMESPACES, true ) || ( self::SVG_NS === $element->namespaceURI && 'metadata' === $element->localName ) ) $remove[] = $element;
		}
		foreach ( $remove as $node ) if ( $node->parentNode ) $node->parentNode->removeChild( $node );
		foreach ( iterator_to_array( $xpath->query( '//@*' ) ) as $attribute ) {
			if ( in_array( $attribute->namespaceURI, self::EDITOR_NAMESPACES, true ) && $attribute->ownerElement ) $attribute->ownerElement->removeAttributeNode( $attribute );
		}
		foreach ( iterator_to_array( $xpath->query( '//text()' ) ) as $text ) {
			if ( '' === trim( $text->nodeValue ) && ! self::keeps_whitespace( $text ) && $text->parentNode ) $text->parentNode->removeChild( $text );
		}
		if ( self::drawing( $doc ) !== $before ) return null;
		$out = (string) $doc->saveXML( $doc->documentElement );
		// Las declaraciones `xmlns:prefijo` que ya nadie usa (las de los editores y el `xmlns:svg` que añade Inkscape).
		if ( preg_match_all( '/\sxmlns:([\w.-]+)="[^"]*"/', $out, $declared ) ) {
			$body = preg_replace( '/\sxmlns:[\w.-]+="[^"]*"/', '', $out );
			foreach ( array_unique( $declared[1] ) as $prefix ) {
				if ( ! preg_match( '/[<\s]' . preg_quote( $prefix, '/' ) . ':/', $body ) ) $out = preg_replace( '/\sxmlns:' . preg_quote( $prefix, '/' ) . '="[^"]*"/', '', $out );
			}
		}
		$check = self::load( $out );
		if ( ! $check || self::drawing( $check ) !== $before || strlen( $out ) >= strlen( $svg ) ) return null;
		return $out;
	}

	/**
	 * Lote de «Optimizar medios»: escribe la versión minimizada junto al original y apunta el adjunto a ella. El
	 * original se conserva, como en WebP, para no romper enlaces antiguos.
	 *
	 * @return array{status:string,message:string,path?:string} status: done, kept o failed.
	 */
	public static function optimize_attachment( $attachment_id, $file ) {
		$name = $file ? wp_basename( $file ) : 'archivo no disponible';
		if ( ! $file || ! is_file( $file ) ) return array( 'status' => 'failed', 'message' => 'No se encontró el archivo original.' );
		$min = self::minify( (string) file_get_contents( $file ) );
		if ( null === $min ) return array( 'status' => 'kept', 'message' => $name . ': ya estaba optimizado o no se puede minimizar sin riesgo; se conserva.' );
		$directory = dirname( $file );
		$output    = $directory . '/' . wp_unique_filename( $directory, pathinfo( $file, PATHINFO_FILENAME ) . '.min.svg' );
		if ( false === file_put_contents( $output, $min ) ) return array( 'status' => 'failed', 'message' => 'No se pudo escribir el SVG minimizado.' );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$metadata = is_array( $metadata ) ? $metadata : array();
		update_attached_file( $attachment_id, $output );
		if ( wp_normalize_path( (string) get_attached_file( $attachment_id ) ) !== wp_normalize_path( $output ) ) {
			update_attached_file( $attachment_id, $file );
			wp_delete_file( $output );
			return array( 'status' => 'failed', 'message' => 'No se pudo cambiar el archivo principal del adjunto.' );
		}
		$metadata['file']     = _wp_relative_upload_path( $output );
		$metadata['filesize'] = strlen( $min );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		$saved = (int) filesize( $file ) - strlen( $min );
		return array( 'status' => 'done', 'path' => $output, 'message' => $name . ' → ' . wp_basename( $output ) . ' minimizado (' . size_format( max( 0, $saved ) ) . ' menos) y aplicado en la biblioteca.' );
	}

	private static function load( $svg ) {
		$previous = libxml_use_internal_errors( true );
		$doc      = new \DOMDocument();
		// Sin red ni entidades externas: el archivo nunca hace peticiones al analizarse.
		$loaded = $doc->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $loaded && $doc->documentElement && 'svg' === $doc->documentElement->localName ? $doc : null;
	}

	/** Recuento de los elementos SVG que se pintan o se referencian: nada de eso puede cambiar. */
	private static function drawing( \DOMDocument $doc ) {
		$count = array();
		foreach ( $doc->getElementsByTagNameNS( self::SVG_NS, '*' ) as $element ) {
			if ( 'metadata' === $element->localName ) continue;
			$count[ $element->localName ] = ( $count[ $element->localName ] ?? 0 ) + 1;
		}
		ksort( $count );
		return $count;
	}

	private static function keeps_whitespace( \DOMNode $node ) {
		for ( $parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode ) {
			if ( in_array( $parent->localName, self::TEXT_ELEMENTS, true ) || 'preserve' === $parent->getAttributeNS( 'http://www.w3.org/XML/1998/namespace', 'space' ) ) return true;
		}
		return false;
	}
}
