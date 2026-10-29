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

	/** Sustituye las variables y normaliza los espacios. */
	public static function replace( $template, array $context = array() ) {
		$template = (string) $template;
		if ( false === strpos( $template, '%' ) ) return trim( preg_replace( '/\s+/', ' ', $template ) );
		$out = preg_replace_callback( '/%([a-z_]+)(?:\(([^)%]*)\))?%/i', function( $m ) use ( $context ) {
			$name = strtolower( $m[1] );
			$arg  = isset( $m[2] ) ? trim( $m[2] ) : '';
			if ( '' !== $arg && ! in_array( $name, self::WITH_ARGS, true ) ) return '';
			return wp_strip_all_tags( (string) self::value( $name, $arg, $context ) );
		}, $template );
		return trim( preg_replace( '/\s+/', ' ', (string) $out ) );
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
