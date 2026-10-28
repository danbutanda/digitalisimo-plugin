<?php
defined( 'ABSPATH' ) || exit;

/**
 * Migas de pan (RM13 de docs/SEO_RANKMATH_OBJECTIVE.md): una sola ruta para el shortcode
 * `[digitalisimo_breadcrumbs]` y el BreadcrumbList del schema.
 *
 * La ruta siempre empieza en Inicio y termina en la página actual; los ajustes de visualización
 * (ocultar Inicio o la página actual) sólo cambian el HTML, nunca el schema.
 */
class Digitalisimo_Integrations_Breadcrumbs {
	private static function o( $key, $default = '' ) { return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default ); }

	private static function item( $name, $url ) { return array( 'name' => wp_strip_all_tags( (string) $name ), 'url' => (string) $url ); }

	private static function home() { return self::item( self::o( 'seo_breadcrumbs_home_label', 'Inicio' ) ?: 'Inicio', home_url( '/' ) ); }

	/** Título de miga propio (editado o importado) o el título del contenido. */
	public static function post_name( $id ) {
		$custom = trim( (string) get_post_meta( $id, 'digitalisimo_seo_breadcrumb_title', true ) );
		return '' !== $custom ? $custom : get_the_title( $id );
	}

	/** Título de miga propio del término (editado o importado) o su nombre. */
	public static function term_name( $term ) {
		$custom = trim( (string) get_term_meta( $term->term_id, 'digitalisimo_seo_breadcrumb_title', true ) );
		return '' !== $custom ? $custom : $term->name;
	}

	/** «Archivo de %s»: el formato admite %s; sin él, se añade el nombre al final. */
	public static function format( $format, $name ) {
		$format = (string) $format;
		return false !== strpos( $format, '%s' ) ? str_replace( '%s', $name, $format ) : trim( $format . ' ' . $name );
	}

	/** Página del blog cuando la portada es estática y el ajuste lo pide. */
	private static function blog_page() {
		$blog = (int) get_option( 'page_for_posts' );
		if ( ! self::o( 'seo_breadcrumbs_blog_page', 1 ) || 'page' !== get_option( 'show_on_front' ) || ! $blog || 'publish' !== get_post_status( $blog ) ) return array();
		return array( self::item( self::post_name( $blog ), get_permalink( $blog ) ) );
	}

	/** Término y sus antecesores, del más general al más concreto. */
	private static function term_chain( $term ) {
		$items = array();
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor ) {
			$parent = get_term( $ancestor, $term->taxonomy );
			if ( $parent && ! is_wp_error( $parent ) ) $items[] = self::item( self::term_name( $parent ), get_term_link( $parent ) );
		}
		$link = get_term_link( $term );
		if ( ! is_wp_error( $link ) ) $items[] = self::item( self::term_name( $term ), $link );
		return $items;
	}

	/** Primera taxonomía jerárquica y pública del tipo (la de las categorías en las entradas). */
	public static function taxonomy_for( $post_type ) {
		if ( 'post' === $post_type ) return 'category';
		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) if ( $taxonomy->hierarchical && $taxonomy->public ) return $taxonomy->name;
		return '';
	}

	/** Inicio › contenido pilar, páginas superiores o archivo y categoría principal › contenido. */
	public static function for_post( $post ) {
		$items  = array( self::home() );
		$id     = (int) $post->ID;
		$pillar = absint( get_post_meta( $id, '_related_pillar', true ) );
		if ( $pillar && $pillar !== $id ) {
			if ( 'publish' === get_post_status( $pillar ) ) $items[] = self::item( self::post_name( $pillar ), get_permalink( $pillar ) );
		} elseif ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $parent ) if ( 'publish' === get_post_status( $parent ) ) $items[] = self::item( self::post_name( $parent ), get_permalink( $parent ) );
		} else {
			if ( 'post' === $post->post_type ) $items = array_merge( $items, self::blog_page() );
			else {
				$object = get_post_type_object( $post->post_type );
				$link   = get_post_type_archive_link( $post->post_type );
				if ( $object && $object->has_archive && $link ) $items[] = self::item( $object->labels->name, $link );
			}
			$taxonomy = self::o( 'seo_breadcrumbs_taxonomy', 1 ) ? self::taxonomy_for( $post->post_type ) : '';
			$term     = $taxonomy ? Digitalisimo_Integrations_SEO_Variables::primary_term( $id, $taxonomy ) : null;
			if ( $term ) $items = array_merge( $items, self::term_chain( $term ) );
		}
		$items[] = self::item( self::post_name( $id ), get_permalink( $post ) );
		return $items;
	}

	/** Ruta de la petición actual. */
	public static function trail() {
		if ( is_front_page() ) return array( self::home() );
		if ( is_singular() ) { $post = get_queried_object(); return $post instanceof WP_Post ? self::for_post( $post ) : array( self::home() ); }
		$items = array( self::home() );
		if ( is_home() ) { $blog = (int) get_option( 'page_for_posts' ); if ( $blog ) $items[] = self::item( self::post_name( $blog ), get_permalink( $blog ) ); return $items; }
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term ) return $items;
			if ( in_array( $term->taxonomy, array( 'category', 'post_tag' ), true ) ) $items = array_merge( $items, self::blog_page() );
			return array_merge( $items, self::term_chain( $term ) );
		}
		$archive = (string) self::o( 'seo_breadcrumbs_archive_format', 'Archivo de %s' );
		if ( is_post_type_archive() ) { $object = get_queried_object(); if ( $object && ! empty( $object->labels ) ) $items[] = self::item( $object->labels->name, get_post_type_archive_link( $object->name ) ); return $items; }
		if ( is_author() ) { $author = get_queried_object(); if ( $author instanceof WP_User ) $items[] = self::item( self::format( $archive, $author->display_name ), get_author_posts_url( $author->ID ) ); return $items; }
		if ( is_date() ) {
			$year  = (int) get_query_var( 'year' );
			$month = (int) get_query_var( 'monthnum' );
			$day   = (int) get_query_var( 'day' );
			$label = $day ? wp_date( get_option( 'date_format' ), mktime( 12, 0, 0, $month, $day, $year ) ) : ( $month ? wp_date( 'F Y', mktime( 12, 0, 0, $month, 1, $year ) ) : (string) $year );
			$link  = $day ? get_day_link( $year, $month, $day ) : ( $month ? get_month_link( $year, $month ) : get_year_link( $year ) );
			$items[] = self::item( self::format( $archive, $label ), $link );
			return $items;
		}
		if ( is_search() ) { $items[] = self::item( self::format( self::o( 'seo_breadcrumbs_search_format', 'Resultados de «%s»' ), get_search_query( false ) ), get_search_link() ); return $items; }
		if ( is_404() ) { $items[] = self::item( self::o( 'seo_breadcrumbs_404_label', 'Página no encontrada' ) ?: 'Página no encontrada', '' ); return $items; }
		return $items;
	}

	/** Lista para BreadcrumbList. */
	public static function schema_items( array $items ) {
		$out = array();
		foreach ( array_values( $items ) as $i => $item ) $out[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name'], 'item' => $item['url'] );
		return $out;
	}

	/**
	 * HTML de las migas a partir de una ruta. Pura respecto a los ajustes que recibe: la prueba la usa sin WordPress.
	 *
	 * @param array $items Ruta completa (Inicio … actual).
	 * @param array $args  separator, prefix, show_home, hide_current.
	 */
	public static function render( array $items, array $args ) {
		if ( ! empty( $args['hide_current'] ) && count( $items ) > 1 ) array_pop( $items );
		if ( empty( $args['show_home'] ) && count( $items ) > 1 ) array_shift( $items );
		if ( ! $items ) return '';
		$separator = '' !== trim( (string) ( $args['separator'] ?? '' ) ) ? (string) $args['separator'] : '›';
		$html  = '<nav class="digitalisimo-breadcrumbs" aria-label="Migas de pan">';
		if ( '' !== trim( (string) ( $args['prefix'] ?? '' ) ) ) $html .= '<span class="digitalisimo-breadcrumbs__prefix">' . esc_html( $args['prefix'] ) . '</span> ';
		$last = count( $items ) - 1;
		foreach ( array_values( $items ) as $i => $item ) {
			if ( $i ) $html .= ' <span class="digitalisimo-breadcrumbs__separator" aria-hidden="true">' . esc_html( $separator ) . '</span> ';
			// La página actual no enlaza a sí misma (salvo que se haya ocultado: entonces el último es un nivel superior).
			$current = $i === $last && empty( $args['hide_current'] );
			$html   .= $current || '' === $item['url'] ? '<span aria-current="page">' . esc_html( $item['name'] ) . '</span>' : '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
		}
		return $html . '</nav>';
	}

	public static function html() {
		if ( ! self::o( 'seo_breadcrumbs', 1 ) ) return '';
		return self::render( self::trail(), array( 'separator' => self::o( 'seo_breadcrumbs_separator', '›' ), 'prefix' => self::o( 'seo_breadcrumbs_prefix', '' ), 'show_home' => self::o( 'seo_breadcrumbs_show_home', 1 ), 'hide_current' => self::o( 'seo_breadcrumbs_hide_current', 0 ) ) );
	}

	/** Primer término jerárquico asignable por tipo: lo que ofrece el selector «categoría principal». */
	public static function primary_choices( $post ) {
		$taxonomy = self::taxonomy_for( $post->post_type );
		if ( ! $taxonomy ) return array( '', array() );
		$terms = get_the_terms( $post->ID, $taxonomy );
		return array( $taxonomy, is_array( $terms ) ? $terms : array() );
	}
}
