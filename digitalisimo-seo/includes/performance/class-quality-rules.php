<?php
defined( 'ABSPATH' ) || exit;

/**
 * Reglas de la auditoría de calidad frontend.
 *
 * El navegador sólo recoge hechos (DOM, nombre accesible, estilos computados,
 * geometría); aquí se decide el estado de cada uno. No dependen de WordPress
 * ni de un sitio concreto: ninguna regla nombra URLs, colores, IDs de Elementor
 * ni páginas. Nada de esto corrige el sitio: sólo informa.
 */
class Digitalisimo_Integrations_Quality_Rules {
	const OK      = 'OK';
	const WARNING = 'ADVERTENCIA';
	const ERROR   = 'ERROR';
	const NA      = 'NO APLICABLE';
	/** WCAG 2.2 · 2.5.8 (AA): 24 × 24 px CSS o separación equivalente. */
	const TARGET_MIN = 24;

	/** Textos que no describen el destino (los que también señala Lighthouse en español e inglés). */
	const GENERIC_ALTS = array( 'imagen', 'image', 'foto', 'fotografia', 'photo', 'picture', 'img', 'logo', 'banner', 'icono', 'icon', 'grafico', 'sin titulo', 'untitled', 'default', 'placeholder', 'captura', 'slide', 'hero', 'fondo' );
	const GENERIC_NAMES = array( 'aqui', 'click aqui', 'clic aqui', 'haz clic aqui', 'haga clic aqui', 'da clic aqui', 'mas', 'ver mas', 'leer mas', 'saber mas', 'mas informacion', 'mas info', 'informacion', 'continuar', 'ir', 'enlace', 'link', 'click here', 'click', 'here', 'more', 'read more', 'learn more', 'more info', 'continue', 'go' );

	/* ------------------------------------------------------------------ *
	 * Utilidades
	 * ------------------------------------------------------------------ */

	public static function text( $value, $max = 160 ) {
		$value = trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/** Comparación de textos sin mayúsculas, acentos, puntuación ni espacios repetidos. */
	public static function normalize( $value ) {
		$value = strtolower( self::fold( trim( (string) $value ) ) );
		$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );
		return trim( (string) $value );
	}

	private static function fold( $value ) {
		$map = array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c', 'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u' );
		$value = strtr( $value, $map );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : $value;
	}

	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
	}

	/** Destino comparable: sin fragmento, host en minúsculas y sin barra final. */
	public static function normalize_url( $url ) {
		$parts = parse_url( trim( (string) $url ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) return '';
		$path = $parts['path'] ?? '/';
		if ( '/' !== $path ) $path = rtrim( $path, '/' );
		$port = isset( $parts['port'] ) && ! in_array( (int) $parts['port'], array( 80, 443 ), true ) ? ':' . (int) $parts['port'] : '';
		return strtolower( $parts['host'] ) . $port . $path . ( isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '' );
	}

	private static function counts( $rows ) {
		$counts = array( 'errors' => 0, 'warnings' => 0 );
		foreach ( (array) $rows as $row ) {
			if ( self::ERROR === ( $row['status'] ?? '' ) ) ++$counts['errors'];
			elseif ( self::WARNING === ( $row['status'] ?? '' ) ) ++$counts['warnings'];
		}
		return $counts;
	}

	/* ------------------------------------------------------------------ *
	 * Enlaces y rastreabilidad
	 * ------------------------------------------------------------------ */

	/**
	 * @param array $link href (null si no existe el atributo), resolved (URL absoluta
	 *                    del navegador), target_exists (ancla presente), submenu.
	 * @return array|null [estado, motivo] o null si el destino es válido.
	 */
	public static function classify_href( $link ) {
		$href = $link['href'] ?? null;
		if ( null === $href ) return array( self::ERROR, 'Sin atributo href: no es un enlace navegable ni rastreable.' );
		$href = trim( (string) $href );
		if ( '' === $href ) return array( self::ERROR, 'href vacío.' );
		if ( '#' === $href ) return ! empty( $link['submenu'] ) ? array( self::WARNING, 'href="#" en un elemento que abre un submenú: no es rastreable.' ) : array( self::ERROR, 'href="#" sin destino.' );
		if ( preg_match( '/^javascript:/i', $href ) ) return array( self::ERROR, 'href="javascript:": no es rastreable.' );
		// Acciones de Elementor (popups, lightbox): intencionales, no son destinos de navegación.
		if ( 0 === stripos( $href, '#elementor-action' ) ) return null;
		if ( '#' === $href[0] ) return false === ( $link['target_exists'] ?? null ) ? array( self::WARNING, 'Ancla sin elemento de destino en esta página.' ) : null;
		if ( preg_match( '/\{\{|%7B%7B|\[[a-z_]+\]|^(?:undefined|null|none)$/i', $href ) ) return array( self::ERROR, 'Destino sin resolver (variable o marcador sin reemplazar).' );
		if ( preg_match( '/^mailto:/i', $href ) ) return preg_match( '/^mailto:[^@\s?]+@[^@\s?]+\.[^@\s?]+/i', $href ) ? null : array( self::ERROR, 'mailto: sin dirección válida.' );
		if ( preg_match( '/^tel:/i', $href ) ) return strlen( preg_replace( '/\D/', '', $href ) ) >= 3 ? null : array( self::ERROR, 'tel: sin número.' );
		if ( preg_match( '/^(?:sms|whatsapp|skype|viber|geo|maps|tg|fb-messenger|webcal):/i', $href ) ) return null;
		if ( preg_match( '/^([a-z][a-z0-9+.-]*):/i', $href, $scheme ) && ! in_array( strtolower( $scheme[1] ), array( 'http', 'https' ), true ) ) return array( self::WARNING, 'Esquema no habitual: ' . strtolower( $scheme[1] ) . ':' );
		if ( '' === self::normalize_url( $link['resolved'] ?? '' ) ) return array( self::ERROR, 'Destino inválido.' );
		if ( preg_match( '/\s/', $href ) ) return array( self::WARNING, 'El href contiene espacios.' );
		return null;
	}

	/** Enlaces sin destino válido. No se inventa ni corrige ninguna URL. */
	public static function link_issues( $links ) {
		$rows = array();
		foreach ( (array) $links as $link ) {
			$verdict = self::classify_href( $link );
			if ( ! $verdict ) continue;
			$kind = ! empty( $link['social'] ) ? 'Icono social' : ( ! empty( $link['button'] ) ? 'Botón' : 'Enlace' );
			$rows[] = array(
				'status' => $verdict[0], 'issue' => $verdict[1], 'kind' => $kind,
				'name' => (string) ( $link['name'] ?? '' ), 'selector' => (string) ( $link['selector'] ?? '' ),
				'href' => null === ( $link['href'] ?? null ) ? '(ausente)' : (string) $link['href'],
				'widget' => (string) ( $link['widget'] ?? '' ), 'hidden' => empty( $link['visible'] ),
			);
		}
		return $rows;
	}

	/** Nombre accesible: vacío, genérico, o incompatible con el destino. */
	public static function name_issues( $links ) {
		$rows  = array();
		$by_url = array();
		$by_name = array();
		foreach ( (array) $links as $link ) {
			if ( empty( $link['visible'] ) ) continue;
			$name = self::text( $link['name'] ?? '', 200 );
			$key  = self::normalize( $name );
			$base = array( 'name' => $name, 'selector' => (string) ( $link['selector'] ?? '' ), 'href' => (string) ( $link['href'] ?? '' ), 'widget' => (string) ( $link['widget'] ?? '' ) );
			if ( '' === $key ) {
				$rows[] = $base + array( 'status' => self::ERROR, 'issue' => ! empty( $link['icon'] ) ? 'Icono sin aria-label ni texto: un lector de pantalla no sabe adónde lleva.' : 'Enlace sin texto accesible.' );
				continue;
			}
			if ( in_array( $key, self::GENERIC_NAMES, true ) ) $rows[] = $base + array( 'status' => self::WARNING, 'issue' => 'Texto genérico: no describe el destino fuera de contexto.' );
			$url = self::normalize_url( $link['resolved'] ?? '' );
			if ( '' === $url || preg_match( '/^#/', (string) ( $link['href'] ?? '' ) ) ) continue;
			$by_url[ $url ]['names'][ $key ] = $name;
			$by_url[ $url ]['href'] = (string) ( $link['resolved'] ?? '' );
			$by_name[ $key ]['urls'][ $url ] = (string) ( $link['resolved'] ?? '' );
			$by_name[ $key ]['name'] = $name;
		}
		foreach ( $by_url as $group ) {
			$names = self::incompatible( array_keys( $group['names'] ) );
			if ( count( $names ) < 2 ) continue;
			$shown = array_values( array_intersect_key( $group['names'], array_flip( $names ) ) );
			$rows[] = array( 'status' => self::WARNING, 'issue' => 'Mismo destino con nombres accesibles distintos.', 'name' => implode( ' · ', $shown ), 'names' => $shown, 'selector' => '', 'href' => $group['href'], 'widget' => '' );
		}
		foreach ( $by_name as $key => $group ) {
			if ( count( $group['urls'] ) < 2 || in_array( $key, self::GENERIC_NAMES, true ) ) continue;
			$rows[] = array( 'status' => self::WARNING, 'issue' => 'Mismo nombre accesible lleva a destinos distintos: el propósito resulta ambiguo.', 'name' => $group['name'], 'selector' => '', 'href' => implode( ' · ', array_values( $group['urls'] ) ), 'urls' => array_values( $group['urls'] ), 'widget' => '' );
		}
		return $rows;
	}

	/** Nombres que no se contienen unos a otros («Contacto» y «Ir a contacto» son compatibles). */
	private static function incompatible( $names ) {
		$kept = array();
		foreach ( $names as $name ) {
			$compatible = false;
			foreach ( $names as $other ) {
				if ( $other !== $name && false !== strpos( ' ' . $other . ' ', ' ' . $name . ' ' ) ) { $compatible = true; break; }
			}
			if ( ! $compatible ) $kept[] = $name;
		}
		return count( $kept ) > 1 ? $kept : array();
	}

	/* ------------------------------------------------------------------ *
	 * Encabezados
	 * ------------------------------------------------------------------ */

	private static function visible_headings( $headings ) {
		return array_values( array_filter( (array) $headings, function( $heading ) {
			$level = (int) ( $heading['level'] ?? 0 );
			return ! empty( $heading['visible'] ) && $level >= 1 && $level <= 6;
		} ) );
	}

	/** Jerarquía de los encabezados visibles. Nunca cambia etiquetas: el diseñador decide. */
	public static function heading_issues( $headings ) {
		$list = self::visible_headings( $headings );
		$rows = array();
		if ( ! $list ) return array( array( 'status' => self::ERROR, 'issue' => 'La página no tiene encabezados visibles.', 'level' => 0, 'text' => '', 'selector' => '', 'widget' => '' ) );
		$h1 = count( array_filter( $list, function( $h ) { return 1 === (int) $h['level']; } ) );
		if ( 0 === $h1 ) $rows[] = array( 'status' => self::ERROR, 'issue' => 'Página sin H1 visible.', 'level' => 0, 'text' => '', 'selector' => '', 'widget' => '' );
		elseif ( $h1 > 1 ) $rows[] = array( 'status' => self::WARNING, 'issue' => 'Hay ' . $h1 . ' H1 visibles: el tema principal de la página queda ambiguo.', 'level' => 1, 'text' => '', 'selector' => '', 'widget' => '' );
		if ( $h1 && 1 !== (int) $list[0]['level'] ) $rows[] = self::heading_row( $list[0], self::WARNING, 'El primer encabezado es H' . (int) $list[0]['level'] . ', antes del H1.' );
		$previous = 0;
		foreach ( $list as $heading ) {
			$level = (int) $heading['level'];
			$text  = self::text( $heading['text'] ?? '', 400 );
			if ( $previous && $level > $previous + 1 ) $rows[] = self::heading_row( $heading, self::WARNING, 'Salto H' . $previous . ' → H' . $level . ': falta un nivel intermedio.' );
			if ( '' === self::normalize( $text ) && empty( $heading['image'] ) ) $rows[] = self::heading_row( $heading, self::ERROR, 'Encabezado vacío.' );
			elseif ( self::length( $text ) > 120 ) $rows[] = self::heading_row( $heading, self::WARNING, 'Encabezado muy largo: parece un párrafo con estilo de título.' );
			elseif ( '' !== $text && ( self::length( $text ) <= 2 || preg_match( '/^[\d\W_]+$/u', $text ) ) ) $rows[] = self::heading_row( $heading, self::WARNING, 'Sólo números o símbolos: parece usado por apariencia visual, no como título de sección.' );
			$previous = $level;
		}
		return $rows;
	}

	private static function heading_row( $heading, $status, $issue ) {
		return array( 'status' => $status, 'issue' => $issue, 'level' => (int) $heading['level'], 'text' => self::text( $heading['text'] ?? '', 120 ), 'selector' => (string) ( $heading['selector'] ?? '' ), 'widget' => (string) ( $heading['widget'] ?? '' ) );
	}

	/** Árbol con ramas «├─ └─ │» de los encabezados visibles. */
	public static function heading_tree( $headings ) {
		$list  = self::visible_headings( $headings );
		$lines = array();
		foreach ( $list as $i => $heading ) {
			$level  = (int) $heading['level'];
			$label  = 'H' . $level . ' ' . self::text( $heading['text'] ?? '', 90 );
			if ( 1 === $level ) { $lines[] = $label; continue; }
			$prefix = '';
			for ( $ancestor = 2; $ancestor < $level; $ancestor++ ) $prefix .= self::continues( $list, $i, $ancestor ) ? '│   ' : '    ';
			$lines[] = $prefix . ( self::continues( $list, $i, $level ) ? '├─ ' : '└─ ' ) . $label;
		}
		return $lines;
	}

	/** ¿Aparece otro encabezado del mismo nivel antes de que cierre la sección padre? */
	private static function continues( $list, $index, $level ) {
		for ( $j = $index + 1, $count = count( $list ); $j < $count; $j++ ) {
			$next = (int) $list[ $j ]['level'];
			if ( $next < $level ) return false;
			if ( $next === $level ) return true;
		}
		return false;
	}

	/* ------------------------------------------------------------------ *
	 * Texto alternativo
	 * ------------------------------------------------------------------ */

	private static function filename_like( $alt ) {
		$alt = trim( (string) $alt );
		if ( preg_match( '/\.(?:jpe?g|png|gif|webp|avif|svg|bmp|tiff?)$/i', $alt ) ) return true;
		if ( preg_match( '/^(?:img|image|imagen|dsc|dscn|pxl|photo|foto|screenshot|captura|whatsapp[ _-]image|untitled|sin[ _-]titulo)[ _-]?\d/i', $alt ) ) return true;
		return strlen( $alt ) > 12 && ! preg_match( '/\s/', $alt ) && preg_match( '/[-_]/', $alt ) && preg_match( '/\d/', $alt );
	}

	/**
	 * @param array $images alt (null si falta), role, aria_hidden, caption, link_text,
	 *                      rect [w, h], classification (informative|decorative|'').
	 */
	public static function alt_issues( $images ) {
		$rows = array();
		$seen = array();
		foreach ( (array) $images as $image ) {
			$key = self::normalize( (string) ( $image['alt'] ?? '' ) );
			if ( ! empty( $image['visible'] ) && '' !== $key ) $seen[ $key ] = ( $seen[ $key ] ?? 0 ) + 1;
		}
		foreach ( (array) $images as $image ) {
			if ( empty( $image['visible'] ) ) continue;
			$rect = (array) ( $image['rect'] ?? array( 0, 0 ) );
			if ( (float) ( $rect[0] ?? 0 ) <= 2 && (float) ( $rect[1] ?? 0 ) <= 2 ) continue; // Píxel de seguimiento.
			$alt   = $image['alt'] ?? null;
			$class = (string) ( $image['classification'] ?? '' );
			$base  = array( 'src' => (string) ( $image['src'] ?? '' ), 'alt' => $alt, 'selector' => (string) ( $image['selector'] ?? '' ), 'widget' => (string) ( $image['widget'] ?? '' ), 'attachment_id' => (int) ( $image['attachment_id'] ?? 0 ), 'classification' => $class );
			$hidden = ! empty( $image['aria_hidden'] ) || in_array( strtolower( (string) ( $image['role'] ?? '' ) ), array( 'presentation', 'none' ), true );
			$key    = self::normalize( (string) $alt );
			if ( $hidden ) {
				if ( '' !== $key ) $rows[] = $base + array( 'status' => self::WARNING, 'issue' => 'Oculta para lectores de pantalla (aria-hidden/role) pero con alt descriptivo.' );
				continue;
			}
			if ( null === $alt ) { $rows[] = $base + array( 'status' => self::ERROR, 'issue' => 'Sin atributo alt: el lector de pantalla lee el nombre del archivo.' ); continue; }
			if ( '' === $key ) {
				if ( 'informative' === $class ) $rows[] = $base + array( 'status' => self::ERROR, 'issue' => 'Clasificada como informativa, pero su alt está vacío.' );
				elseif ( 'decorative' !== $class && (float) ( $rect[0] ?? 0 ) >= 300 ) $rows[] = $base + array( 'status' => self::WARNING, 'issue' => 'Alt vacío en una imagen grande: confirma si es decorativa o necesita descripción.' );
				continue;
			}
			if ( 'decorative' === $class ) { $rows[] = $base + array( 'status' => self::WARNING, 'issue' => 'Clasificada como decorativa, pero esta salida no pasó por el plugin y conserva su alt.' ); continue; }
			if ( '' !== self::normalize( $image['caption'] ?? '' ) && self::normalize( $image['caption'] ) === $key ) $rows[] = $base + array( 'status' => self::WARNING, 'issue' => 'Alt idéntico al caption inmediato: se lee dos veces.' );
			elseif ( '' !== self::normalize( $image['link_text'] ?? '' ) && self::normalize( $image['link_text'] ) === $key ) $rows[] = $base + array( 'status' => self::WARNING, 'issue' => 'Alt idéntico al texto del enlace: se lee dos veces.' );
			elseif ( self::filename_like( $alt ) ) $rows[] = $base + array( 'status' => self::WARNING, 'issue' => 'El alt parece un nombre de archivo.' );
			elseif ( in_array( $key, self::GENERIC_ALTS, true ) ) $rows[] = $base + array( 'status' => self::WARNING, 'issue' => 'ALT posiblemente genérico: no describe esta imagen.' );
			elseif ( ( $seen[ $key ] ?? 0 ) > 1 ) $rows[] = $base + array( 'status' => self::WARNING, 'issue' => 'ALT repetido en ' . $seen[ $key ] . ' imágenes de esta página.' );
			elseif ( self::length( $alt ) > 150 ) $rows[] = $base + array( 'status' => self::WARNING, 'issue' => 'Alt muy largo: conviene una descripción breve y el detalle en el texto.' );
		}
		return $rows;
	}

	/* ------------------------------------------------------------------ *
	 * Objetivos táctiles
	 * ------------------------------------------------------------------ */

	private static function contains( $outer, $inner ) {
		return $outer['x'] <= $inner['x'] + 0.5 && $outer['y'] <= $inner['y'] + 0.5 && $outer['x'] + $outer['w'] >= $inner['x'] + $inner['w'] - 0.5 && $outer['y'] + $outer['h'] >= $inner['y'] + $inner['h'] - 0.5;
	}

	private static function distance_to_rect( $px, $py, $rect ) {
		$dx = max( $rect['x'] - $px, 0, $px - ( $rect['x'] + $rect['w'] ) );
		$dy = max( $rect['y'] - $py, 0, $py - ( $rect['y'] + $rect['h'] ) );
		return sqrt( $dx * $dx + $dy * $dy );
	}

	/**
	 * WCAG 2.5.8: un control menor de 24 × 24 px cumple si el círculo de 24 px
	 * centrado en él no toca otro control. Los enlaces dentro de un párrafo están
	 * exentos. Sólo se informa: el tamaño pertenece al diseño.
	 */
	public static function touch_issues( $targets ) {
		$list = array();
		foreach ( (array) $targets as $target ) {
			$item = array( 'x' => (float) ( $target['x'] ?? 0 ), 'y' => (float) ( $target['y'] ?? 0 ), 'w' => (float) ( $target['w'] ?? 0 ), 'h' => (float) ( $target['h'] ?? 0 ) ) + $target;
			if ( $item['w'] <= 0 || $item['h'] <= 0 ) continue;
			$list[] = $item;
		}
		$rows = array();
		$half = self::TARGET_MIN / 2;
		foreach ( $list as $i => $target ) {
			if ( ! empty( $target['inline'] ) || ( $target['w'] >= self::TARGET_MIN && $target['h'] >= self::TARGET_MIN ) ) continue;
			$cx = $target['x'] + $target['w'] / 2;
			$cy = $target['y'] + $target['h'] / 2;
			$gap = null;
			$conflict = false;
			foreach ( $list as $j => $other ) {
				if ( $i === $j || self::contains( $other, $target ) || self::contains( $target, $other ) ) continue;
				$small = $other['w'] < self::TARGET_MIN || $other['h'] < self::TARGET_MIN;
				$distance = $small ? sqrt( pow( $cx - ( $other['x'] + $other['w'] / 2 ), 2 ) + pow( $cy - ( $other['y'] + $other['h'] / 2 ), 2 ) ) : self::distance_to_rect( $cx, $cy, $other );
				$limit = $small ? self::TARGET_MIN : $half;
				if ( $distance < $limit ) $conflict = true;
				$gap = null === $gap ? $distance : min( $gap, $distance );
			}
			$rows[] = array(
				'status' => $conflict ? self::ERROR : self::WARNING,
				'issue'  => $conflict ? 'Menor de 24 × 24 px y demasiado cerca de otro control.' : 'Menor de 24 × 24 px; la separación con otros controles es suficiente.',
				'name' => (string) ( $target['name'] ?? '' ), 'selector' => (string) ( $target['selector'] ?? '' ), 'widget' => (string) ( $target['widget'] ?? '' ),
				'size' => round( $target['w'] ) . ' × ' . round( $target['h'] ) . ' px', 'gap' => null === $gap ? null : (int) round( $gap ),
			);
		}
		return $rows;
	}

	/* ------------------------------------------------------------------ *
	 * Contraste
	 * ------------------------------------------------------------------ */

	/** rgb()/rgba() tal como los devuelve getComputedStyle, también con la sintaxis de espacios. */
	public static function parse_color( $value ) {
		if ( ! preg_match( '/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:\s*[,\/]\s*([\d.]+%?))?\s*\)$/i', trim( (string) $value ), $m ) ) return null;
		$alpha = isset( $m[4] ) && '' !== $m[4] ? ( '%' === substr( $m[4], -1 ) ? (float) $m[4] / 100 : (float) $m[4] ) : 1.0;
		return array( min( 255, (float) $m[1] ), min( 255, (float) $m[2] ), min( 255, (float) $m[3] ), max( 0.0, min( 1.0, $alpha ) ) );
	}

	private static function luminance( $rgb ) {
		$channels = array();
		foreach ( array( 0, 1, 2 ) as $i ) {
			$c = $rgb[ $i ] / 255;
			$channels[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	public static function contrast_ratio( $foreground, $background ) {
		$a = self::luminance( $foreground );
		$b = self::luminance( $background );
		return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
	}

	public static function hex( $rgb ) {
		return sprintf( '#%02x%02x%02x', round( $rgb[0] ), round( $rgb[1] ), round( $rgb[2] ) );
	}

	/**
	 * @param array $samples fg, bg (null si hay imagen o degradado detrás), opacity, size (px), weight.
	 * @return array rows (sólo los que no alcanzan AA), checked, unknown.
	 */
	public static function contrast_issues( $samples ) {
		$rows = array();
		$checked = 0;
		$unknown = 0;
		foreach ( (array) $samples as $sample ) {
			$fg = self::parse_color( $sample['fg'] ?? '' );
			$bg = self::parse_color( $sample['bg'] ?? '' );
			if ( ! $fg ) continue;
			if ( ! $bg ) { ++$unknown; continue; }
			$alpha = $fg[3] * max( 0.0, min( 1.0, (float) ( $sample['opacity'] ?? 1 ) ) );
			if ( $alpha < 0.1 ) continue; // Texto prácticamente invisible: no es contenido legible.
			$shown = array( $fg[0] * $alpha + $bg[0] * ( 1 - $alpha ), $fg[1] * $alpha + $bg[1] * ( 1 - $alpha ), $fg[2] * $alpha + $bg[2] * ( 1 - $alpha ) );
			$size  = (float) ( $sample['size'] ?? 16 );
			$large = $size >= 24 || ( $size >= 18.66 && (int) ( $sample['weight'] ?? 400 ) >= 700 );
			$expected = $large ? 3.0 : 4.5;
			$ratio = self::contrast_ratio( $shown, $bg );
			++$checked;
			if ( $ratio + 0.005 >= $expected ) continue;
			$rows[] = array(
				'status' => self::ERROR, 'issue' => 'Contraste ' . number_format( $ratio, 2 ) . ':1, se esperan ' . number_format( $expected, 1 ) . ':1 (AA, texto ' . ( $large ? 'grande' : 'normal' ) . ').',
				'text' => self::text( $sample['text'] ?? '', 80 ), 'selector' => (string) ( $sample['selector'] ?? '' ), 'widget' => (string) ( $sample['widget'] ?? '' ),
				'color' => self::hex( $shown ), 'background' => self::hex( $bg ), 'ratio' => round( $ratio, 2 ), 'expected' => $expected, 'level' => $large ? 'AA texto grande' : 'AA texto normal',
			);
		}
		return array( 'rows' => $rows, 'checked' => $checked, 'unknown' => $unknown );
	}

	/* ------------------------------------------------------------------ *
	 * Imágenes medidas en el navegador
	 * ------------------------------------------------------------------ */

	/** Anchos «w» de un srcset, de menor a mayor. */
	public static function srcset_widths( $srcset ) {
		$widths = array();
		foreach ( explode( ',', (string) $srcset ) as $candidate ) if ( preg_match( '/(?:^|\s)(\d+)w$/', trim( $candidate ), $m ) ) $widths[] = (int) $m[1];
		sort( $widths );
		return array_values( array_unique( $widths ) );
	}

	/**
	 * Compara el archivo descargado con el tamaño pintado por la DPR. Sólo se
	 * advierte cuando WordPress ya tiene una variante menor suficiente: entonces
	 * lo que falla es sizes, y se corrige ahí, nunca cambiando el src.
	 */
	public static function image_issue( $image, $dpr ) {
		$natural = (array) ( $image['natural'] ?? array( 0, 0 ) );
		$rect    = (array) ( $image['rect'] ?? array( 0, 0 ) );
		$nw = (int) ( $natural[0] ?? 0 );
		$nh = (int) ( $natural[1] ?? 0 );
		$rw = (float) ( $rect[0] ?? 0 );
		$rh = (float) ( $rect[1] ?? 0 );
		if ( $nw <= 0 || $rw <= 0 ) return null;
		$dpr    = max( 1.0, (float) $dpr );
		$width  = 'cover' === ( $image['fit'] ?? '' ) && $nh > 0 ? max( $rw, $rh * $nw / $nh ) : $rw;
		$needed = (int) ceil( $width * $dpr );
		$result = array( 'needed' => $needed, 'candidate' => null, 'status' => self::OK, 'issue' => '' );
		if ( $nw <= $needed * 1.2 || $nw - $needed < 100 ) return $result;
		$candidate = null;
		foreach ( self::srcset_widths( $image['srcset'] ?? '' ) as $w ) if ( $w >= $needed ) { $candidate = $w; break; }
		if ( null !== $candidate && $candidate < $nw ) return array( 'needed' => $needed, 'candidate' => $candidate, 'status' => self::WARNING, 'issue' => 'Se descargan ' . $nw . ' px y bastaría la variante de ' . $candidate . ' px: el sizes declara más ancho del que ocupa.' );
		if ( '' === trim( (string) ( $image['srcset'] ?? '' ) ) ) return array( 'needed' => $needed, 'candidate' => null, 'status' => self::WARNING, 'issue' => 'Se descargan ' . $nw . ' px para ' . $needed . ' px y no hay srcset: activa la optimización de imágenes o elige un tamaño menor en Elementor.' );
		return $result;
	}

	/* ------------------------------------------------------------------ *
	 * CSS diferible por página
	 * ------------------------------------------------------------------ */

	/**
	 * @param array $row handle, eligible (CSS de widget aceptado por la lista), selected,
	 *                   enabled (diferido activo), present, above (bool|null por viewport).
	 */
	public static function css_verdict( $row ) {
		$above = ! empty( $row['above_desktop'] ) || ! empty( $row['above_mobile'] );
		$known = null !== ( $row['above_desktop'] ?? null ) || null !== ( $row['above_mobile'] ?? null );
		$state = ! empty( $row['selected'] ) ? ( ! empty( $row['enabled'] ) ? 'DIFERIDO' : 'EN LISTA · diferido desactivado' ) : 'NORMAL';
		if ( empty( $row['eligible'] ) ) return array( 'state' => $state, 'safe' => false, 'status' => self::NA, 'reason' => 'No es una hoja de un widget de Elementor apta para diferir.' );
		if ( empty( $row['present'] ) ) return array( 'state' => $state, 'safe' => false, 'status' => self::NA, 'reason' => 'El widget no aparece en esta página.' );
		if ( ! $known ) return array( 'state' => $state, 'safe' => false, 'status' => self::NA, 'reason' => 'Sin medición del navegador.' );
		if ( $above ) return array( 'state' => $state, 'safe' => false, 'status' => 'DIFERIDO' === $state ? self::ERROR : self::OK, 'reason' => 'El widget aparece en el primer viewport' . ( ! empty( $row['above_mobile'] ) && empty( $row['above_desktop'] ) ? ' móvil' : '' ) . ': diferirlo provoca un salto visual.' );
		return array( 'state' => $state, 'safe' => true, 'status' => self::OK, 'reason' => 'Sólo aparece debajo del primer viewport en escritorio y móvil.' );
	}

	/* ------------------------------------------------------------------ *
	 * Resumen
	 * ------------------------------------------------------------------ */

	private static function row( $label, $rows, $note = '', $applicable = true ) {
		if ( ! $applicable ) return array( 'label' => $label, 'status' => self::NA, 'errors' => 0, 'warnings' => 0, 'note' => $note );
		$counts = self::counts( $rows );
		$status = $counts['errors'] ? self::ERROR : ( $counts['warnings'] ? self::WARNING : self::OK );
		return array( 'label' => $label, 'status' => $status ) + $counts + array( 'note' => $note );
	}

	/**
	 * @param array $findings links, names, headings, alt, touch, contrast, images, css (filas ya evaluadas).
	 * @param array $llms     enabled, valid, fallback.
	 */
	public static function summarize( $findings, $llms ) {
		$f = $findings + array( 'links' => array(), 'names' => array(), 'headings' => array(), 'alt' => array(), 'touch' => array(), 'contrast' => array(), 'images' => array(), 'css' => array(), 'measured' => false );
		$llms_row = empty( $llms['enabled'] ) ? array( 'label' => 'llms.txt', 'status' => self::NA, 'errors' => 0, 'warnings' => 0, 'note' => 'Desactivado en este sitio.' )
			: ( empty( $llms['valid'] ) ? array( 'label' => 'llms.txt', 'status' => self::ERROR, 'errors' => 1, 'warnings' => 0, 'note' => 'La salida no es válida.' )
			: ( ! empty( $llms['fallback'] ) ? array( 'label' => 'llms.txt', 'status' => self::WARNING, 'errors' => 0, 'warnings' => 1, 'note' => 'El contenido manual no es válido: se sirve el automático.' )
			: array( 'label' => 'llms.txt', 'status' => self::OK, 'errors' => 0, 'warnings' => 0, 'note' => 'Markdown válido con H1 y enlaces.' ) ) );
		return array(
			'performance' => self::row( 'Rendimiento', array_merge( $f['images'], $f['css'] ), 'Imágenes sobredimensionadas y CSS diferido en el primer viewport.', $f['measured'] ),
			'a11y'        => self::row( 'Accesibilidad', array_merge( $f['names'], $f['alt'], $f['touch'], $f['contrast'] ), 'Nombres de enlaces, alt, objetivos táctiles y contraste.', $f['measured'] ),
			'seo'         => self::row( 'SEO técnico', array_merge( $f['links'], $f['headings'] ), 'Enlaces rastreables y jerarquía de encabezados.', $f['measured'] ),
			'images'      => self::row( 'Imágenes', array_merge( $f['alt'], $f['images'] ), 'Alt y tamaño descargado frente al tamaño pintado.', $f['measured'] ),
			'llms'        => $llms_row,
			'links'       => self::row( 'Enlaces', $f['links'], 'Destinos ausentes, vacíos, «#» o inválidos.', $f['measured'] ),
			'headings'    => self::row( 'Encabezados', $f['headings'], 'H1 y saltos de nivel.', $f['measured'] ),
		);
	}
}
