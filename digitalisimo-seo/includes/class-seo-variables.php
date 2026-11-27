<?php
defined( 'ABSPATH' ) || exit;

/**
 * Variables de las plantillas de título y descripción (RM10 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Cada valor se calcula sólo si la plantilla lo usa. Una variable desconocida se quita: nunca se
 * imprime literal. Contexto: title, excerpt, term, description (los de siempre) y, si hay, id
 * (contenido) y term_id (término).
 */
class Digitalisimo_Integrations_SEO_Variables {
	/** Nombre => descripción, en el orden de la ayuda de Títulos. */
	public static function catalog() {
		return array(
			'title'            => 'Título del contenido, página o archivo actual.',
			'sitename'         => 'Nombre SEO del sitio; si no existe, el nombre de WordPress.',
			'sitedesc'         => 'Descripción corta (lema) del sitio.',
			'sep'              => 'Separador de títulos configurado en Identidad.',
			'excerpt'          => 'Extracto del contenido; si no hay, el resumen disponible.',
			'term'             => 'Nombre de la categoría, etiqueta u otro término consultado.',
			'description'      => 'Descripción del término o archivo actual.',
			'page'             => 'Número de página de la paginación, sólo cuando aplica.',
			'parent_title'     => 'Título de la página superior.',
			'focuskw'          => 'Keyword principal del contenido.',
			'category'         => 'Categoría principal del contenido (o la primera).',
			'primary_category' => 'Igual que %category%.',
			'tag'              => 'Primera etiqueta del contenido.',
			'tags'             => 'Todas las etiquetas del contenido, separadas por comas.',
			'pt_single'        => 'Nombre en singular del tipo de contenido.',
			'pt_plural'        => 'Nombre en plural del tipo de contenido.',
			'name'             => 'Nombre público del autor.',
			'user_description' => 'Biografía del autor, en su archivo.',
			'date'             => 'Fecha de publicación; admite formato: %date(Y)%.',
			'modified'         => 'Fecha de la última modificación; admite formato: %modified(j F Y)%.',
			'currentdate'      => 'Fecha de hoy; admite formato.',
			'currentyear'      => 'Año en curso.',
			'currentmonth'     => 'Mes en curso.',
			'search_query'     => 'Texto buscado, en los resultados de búsqueda.',
			'org_name'         => 'Nombre de la organización (Identidad).',
			'customfield'      => 'Un campo personalizado público: %customfield(clave)%.',
		);
	}

	/** Variables que admiten argumento entre paréntesis. */
	const WITH_ARGS = array( 'date', 'modified', 'currentdate', 'customfield' );

	/** Separadores que una plantilla suele poner entre variables (incluye todos los elegibles en Identidad). */
	const SEPARATORS = array( '|', '-', '–', '—', '·', '•', '/', '»', '«', '~', '*', '⋆' );

	/** Marca de una variable que quedó vacía, mientras se limpian sus separadores. */
	const EMPTY_SLOT = "\x1E";

	/**
	 * Sustituye las variables y normaliza los espacios.
	 *
	 * Una variable vacía se lleva el separador que la unía al resto: «%focuskw% %sep% %excerpt%» sin keyword ni extracto
	 * daba «|», y con keyword sola, «Web Hosting IA |» (auditoría del 2026-11-24). Sólo se quitan los separadores pegados
	 * a una variable vacía: el texto escrito a mano (sin variables) sale tal cual.
	 */
	public static function replace( $template, array $context = array() ) {
		$template = self::utf8( $template );
		if ( false === strpos( $template, '%' ) ) return trim( self::re( '/\s+/u', ' ', $template ) );
		$out = preg_replace_callback( '/%([a-z_]+)(?:\(([^)%]*)\))?%/i', function( $m ) use ( $context ) {
			$name = strtolower( $m[1] );
			$arg  = isset( $m[2] ) ? trim( $m[2] ) : '';
			if ( '' !== $arg && ! in_array( $name, self::WITH_ARGS, true ) ) return self::EMPTY_SLOT;
			$value = trim( wp_strip_all_tags( str_replace( self::EMPTY_SLOT, '', self::utf8( self::value( $name, $arg, $context ) ) ) ) );
			return '' === $value ? self::EMPTY_SLOT : $value;
		}, $template );
		return self::drop_empty_slots( null === $out ? $template : (string) $out );
	}

	/**
	 * UTF-8 válido. Con un byte inválido (un título pegado de otra codificación) las expresiones con /u devuelven null y
	 * el título o la descripción quedaban vacíos (revisión del 2026-11-25): se quitan sólo los bytes inválidos.
	 */
	public static function utf8( $text ) {
		$text = (string) $text;
		if ( '' === $text || 1 === preg_match( '//u', $text ) ) return $text;
		if ( function_exists( 'iconv' ) ) {
			$clean = @iconv( 'UTF-8', 'UTF-8//IGNORE', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- avisa del byte que quita.
			if ( is_string( $clean ) && 1 === preg_match( '//u', $clean ) ) return $clean;
		}
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$clean = (string) mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
			if ( 1 === preg_match( '//u', $clean ) ) return $clean;
		}
		return (string) preg_replace( '/[\x80-\xFF]/', '', $text );
	}

	/** preg_replace que, si falla (null), devuelve el texto como estaba en lugar de vaciarlo. */
	public static function re( $pattern, $with, $subject ) {
		$out = preg_replace( $pattern, $with, (string) $subject );
		return null === $out ? (string) $subject : $out;
	}

	/** Quita las variables vacías y el separador que las acompañaba (al principio, al final o entre dos separadores). */
	public static function drop_empty_slots( $text ) {
		$text = self::utf8( $text );
		if ( false === strpos( $text, self::EMPTY_SLOT ) ) return trim( self::re( '/\s+/u', ' ', $text ) );
		// Los separadores elegibles en Identidad (|, -, –, ·) y los que importa Rank Math ya están en la lista.
		$seps = self::SEPARATORS;
		// Un separador es una palabra suelta: «Wi-Fi» o «24/7» no lo son.
		$sep  = '(?<![^\s\x{1E}])(?:' . implode( '|', array_map( function( $s ) { return preg_quote( $s, '/' ); }, $seps ) ) . ')(?![^\s\x{1E}])';
		$slot = '\x{1E}';
		$rules = array(
			'/' . $slot . '(?:\s*' . $slot . ')+/u'                  => self::EMPTY_SLOT,
			'/' . $sep . '\s*' . $slot . '\s*(?=' . $sep . ')/u'      => '',
			'/^\s*' . $slot . '\s*' . $sep . '/u'                     => '',
			'/' . $sep . '\s*' . $slot . '\s*$/u'                     => '',
		);
		for ( $i = 0; $i < 5; $i++ ) {
			$before = $text;
			foreach ( $rules as $pattern => $with ) $text = self::re( $pattern, $with, $text );
			if ( $before === $text ) break;
		}
		$text = str_replace( self::EMPTY_SLOT, '', $text );
		return trim( self::re( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Resumen del contenido para la meta descripción, en `$max` caracteres o menos: el texto visible, sin shortcodes
	 * (tampoco los de plugins que ya no están), sin scripts ni estilos y con un espacio entre bloques, para que dos
	 * párrafos no se peguen. Se corta en la última palabra completa.
	 */
	public static function summary( $post, $max = 155 ) {
		if ( ! $post ) return '';
		$html = self::utf8( $post->post_content );
		if ( '' === trim( $html ) ) return '';
		$html = (string) preg_replace( '#<(script|style|noscript|template|svg)\b[^>]*>.*?</\1>#is', ' ', $html );
		$html = (string) preg_replace( '#<!--.*?-->#s', ' ', $html );
		if ( function_exists( 'strip_shortcodes' ) ) $html = strip_shortcodes( $html );
		$html = (string) preg_replace( '/\[\/?[a-z][a-z0-9_\-]*(?:\s[^\]]*)?\]/i', ' ', $html );
		$html = (string) preg_replace( '/<[^>]+>/', ' ', $html );
		$text = self::utf8( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
		$text = trim( self::re( '/\s+/u', ' ', $text ) );
		return self::cut( $text, $max );
	}

	/** Corta en la última palabra completa que cabe, con «…» si se cortó. */
	public static function cut( $text, $max = 155 ) {
		$text = trim( self::utf8( $text ) );
		$max  = max( 20, (int) $max );
		if ( mb_strlen( $text, 'UTF-8' ) <= $max ) return $text;
		$cut   = mb_substr( $text, 0, $max - 1, 'UTF-8' );
		$words = self::re( '/\s+\S*$/u', '', $cut );
		if ( mb_strlen( $words, 'UTF-8' ) >= (int) ( $max * 0.6 ) ) $cut = $words;
		return self::re( '/[\s,;:|·\-–—\/]+$/u', '', $cut ) . '…';
	}

	/** Dos textos dicen lo mismo (sin mayúsculas, entidades, etiquetas ni espacios de más). */
	public static function same_text( $a, $b ) {
		$norm = function( $s ) { return mb_strtolower( trim( self::re( '/\s+/u', ' ', self::utf8( html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' ) ) ) ), 'UTF-8' ); };
		return '' !== $norm( $a ) && $norm( $a ) === $norm( $b );
	}

	private static function o( $key, $default = '' ) { return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default ); }

	private static function post( array $context ) { return ! empty( $context['id'] ) ? get_post( (int) $context['id'] ) : null; }

	/** Término principal de un contenido en una taxonomía: el elegido si sigue asignado, si no el primero. */
	public static function primary_term( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) || ! $terms ) return null;
		$chosen = absint( get_post_meta( $post_id, 'digitalisimo_seo_primary_' . $taxonomy, true ) );
		foreach ( $terms as $term ) if ( (int) $term->term_id === $chosen ) return $term;
		return $terms[0];
	}

	private static function value( $name, $arg, array $context ) {
		switch ( $name ) {
			case 'title': return $context['title'] ?? '';
			case 'term': return $context['term'] ?? '';
			case 'excerpt': return $context['excerpt'] ?? '';
			case 'description': return $context['description'] ?? '';
			case 'user_description': return $context['user_description'] ?? '';
			case 'sitename': return self::o( 'seo_site_name' ) ?: get_bloginfo( 'name' );
			case 'sitedesc': return get_bloginfo( 'description' );
			case 'sep': return self::o( 'seo_separator' ) ?: '|';
			case 'page': return is_paged() ? 'Página ' . (int) get_query_var( 'paged' ) : '';
			case 'org_name': return self::o( 'seo_organization_name' ) ?: ( self::o( 'seo_site_name' ) ?: get_bloginfo( 'name' ) );
			case 'search_query': return is_search() ? get_search_query( false ) : '';
			case 'currentyear': return wp_date( 'Y' );
			case 'currentmonth': return wp_date( 'F' );
			case 'currentdate': return wp_date( '' !== $arg ? $arg : get_option( 'date_format' ) );
		}
		if ( 'name' === $name && isset( $context['name'] ) ) return $context['name'];
		$post = self::post( $context );
		if ( ! $post ) return '';
		switch ( $name ) {
			case 'parent_title': return $post->post_parent ? get_the_title( $post->post_parent ) : '';
			case 'focuskw': $keywords = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post->ID, 'digitalisimo_seo_keywords', true ) ) ) ); return $keywords ? reset( $keywords ) : '';
			case 'category':
			case 'primary_category': $term = self::primary_term( $post->ID, 'category' ); return $term ? $term->name : '';
			case 'tag': $tags = get_the_terms( $post->ID, 'post_tag' ); return is_array( $tags ) && $tags ? $tags[0]->name : '';
			case 'tags': $tags = get_the_terms( $post->ID, 'post_tag' ); return is_array( $tags ) ? implode( ', ', wp_list_pluck( $tags, 'name' ) ) : '';
			case 'pt_single': $type = get_post_type_object( $post->post_type ); return $type ? $type->labels->singular_name : '';
			case 'pt_plural': $type = get_post_type_object( $post->post_type ); return $type ? $type->labels->name : '';
			case 'name': return get_the_author_meta( 'display_name', (int) $post->post_author );
			case 'date': return get_the_date( '' !== $arg ? $arg : '', $post );
			case 'modified': return get_the_modified_date( '' !== $arg ? $arg : '', $post );
			case 'customfield':
				// Sólo metas públicas: las que empiezan por _ o por el prefijo propio son internas.
				$key = sanitize_key( $arg );
				if ( '' === $key || '_' === $key[0] || 0 === strpos( $key, 'digitalisimo_' ) || is_protected_meta( $key, 'post' ) ) return '';
				$value = get_post_meta( $post->ID, $key, true );
				return is_scalar( $value ) ? (string) $value : '';
		}
		return '';
	}
}
