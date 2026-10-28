<?php
defined( 'ABSPATH' ) || exit;

/** Sitemap del sitio actual; nunca mezcla entradas de otros blogs de la red. */
class Digitalisimo_Integrations_Sitemap {
	const BATCH = 250;

	const NEWS_FILE  = 'news-sitemap.xml';
	const NEWS_HOURS = 48;
	const NEWS_LIMIT = 1000;

	const VIDEO_FILE  = 'video-sitemap.xml';
	const VIDEO_LIMIT = 1000;

	public static function init() {
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 0 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 99, 2 );
		add_action( 'wp_head', array( __CLASS__, 'news_robots' ), 1 );
		add_shortcode( 'digitalisimo_sitemap', array( __CLASS__, 'html_shortcode' ) );
	}

	public static function enabled() {
		return (bool) get_option( 'blog_public', 1 ) && (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'sitemap_enabled', 1 );
	}

	public static function endpoint( $filename = 'sitemap.xml' ) {
		return home_url( '/' . $filename );
	}

	/** Nombres humanos derivados del nombre público del tipo, sin paginación técnica. */
	public static function filenames() {
		$types = Digitalisimo_Integrations_SEO_Suite::sitemap_posts( get_post_types( array( 'public' => true ), 'objects' ) );
		// Reservar siempre los nombres conocidos antes de resolver posibles colisiones de CPT.
		$ordered = array();
		foreach ( array( 'page', 'post', 'product' ) as $type ) if ( isset( $types[ $type ] ) ) $ordered[ $type ] = $types[ $type ];
		$types = $ordered + $types;
		$files = array();
		$used  = array( 'sitemap' => true );
		foreach ( $types as $type => $object ) {
			if ( 'page' === $type ) $name = 'paginas';
			elseif ( 'post' === $type ) $name = 'articulos';
			elseif ( 'product' === $type ) $name = 'productos';
			else $name = sanitize_title( $object->labels->name ?? $object->label ?? $type );
			if ( ! $name ) $name = sanitize_title( $type );
			if ( isset( $used[ $name ] ) ) {
				$singular = sanitize_title( $object->labels->singular_name ?? $type );
				$name .= '-' . $singular;
			}
			if ( isset( $used[ $name ] ) ) $name .= '-' . sanitize_title( $type );
			$used[ $name ] = true;
			$files[ $type ] = $name . '.xml';
		}
		return $files;
	}

	/** Coincidencia exacta con la ruta del blog en curso, incluso en subdirectorios. */
	public static function requested() {
		$path = rawurldecode( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
		$base = (string) wp_parse_url( self::endpoint(), PHP_URL_PATH );
		$directory = substr( $base, 0, -strlen( 'sitemap.xml' ) );
		if ( $path === $base || $path === $base . '/' ) return array( 'type' => 'main', 'slash' => $path !== $base );
		if ( $path === $directory . 'wp-sitemap.xml' ) return array( 'type' => 'legacy', 'slash' => false );
		// Noticias: un archivo propio por sitio, también en una red.
		if ( $path === $directory . self::NEWS_FILE || $path === $directory . self::NEWS_FILE . '/' ) return array( 'type' => 'news', 'slash' => '/' === substr( $path, -1 ) );
		if ( $path === $directory . self::VIDEO_FILE || $path === $directory . self::VIDEO_FILE . '/' ) return array( 'type' => 'video', 'slash' => '/' === substr( $path, -1 ) );
		if ( is_multisite() ) return array();
		foreach ( self::filenames() as $type => $filename ) {
			if ( $path === $directory . $filename || $path === $directory . $filename . '/' ) return array( 'type' => $type, 'slash' => '/' === substr( $path, -1 ) );
		}
		return array();
	}

	public static function maybe_serve() {
		$route = self::requested();
		if ( ! $route ) return;
		if ( ! self::enabled() ) self::not_found();
		if ( 'legacy' === $route['type'] ) { wp_redirect( self::endpoint(), 301 ); exit; }
		if ( 'news' === $route['type'] ) {
			if ( ! self::news_enabled() ) self::not_found();
			if ( $route['slash'] ) { wp_redirect( self::endpoint( self::NEWS_FILE ), 301 ); exit; }
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: application/xml; charset=UTF-8' );
			self::news_xml();
			exit;
		}
		if ( 'video' === $route['type'] ) {
			if ( ! self::video_enabled() ) self::not_found();
			if ( $route['slash'] ) { wp_redirect( self::endpoint( self::VIDEO_FILE ), 301 ); exit; }
			status_header( 200 );
			nocache_headers();
			header( 'Content-Type: application/xml; charset=UTF-8' );
			self::video_xml();
			exit;
		}
		if ( $route['slash'] ) {
			$filename = 'main' === $route['type'] ? 'sitemap.xml' : self::filenames()[ $route['type'] ];
			wp_redirect( self::endpoint( $filename ), 301 ); exit;
		}
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		if ( 'main' === $route['type'] && ! is_multisite() ) self::index_xml();
		else self::urls_xml( 'main' === $route['type'] ? array_keys( self::filenames() ) : array( $route['type'] ) );
		exit;
	}

	private static function not_found() {
		status_header( 404 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo "Sitemap no disponible.\n";
		exit;
	}

	public static function robots( $output, $public ) {
		if ( ! $public || ! self::enabled() ) return $output;
		// Un solo sitemap del sitio actual, aunque otro filtro haya añadido el índice nativo.
		$output = preg_replace( '/^[ \t]*Sitemap[ \t]*:[^\r\n]*(?:\r?\n|$)/im', '', (string) $output );
		return rtrim( $output ) . "\nSitemap: " . self::endpoint() . "\n" . ( self::news_enabled() ? 'Sitemap: ' . self::endpoint( self::NEWS_FILE ) . "\n" : '' ) . ( self::video_enabled() ? 'Sitemap: ' . self::endpoint( self::VIDEO_FILE ) . "\n" : '' );
	}

	private static function args( $type, $page, $limit = self::BATCH ) {
		$args = array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => $limit, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'has_password' => false, 'ignore_sticky_posts' => true, 'suppress_filters' => false );
		$args = Digitalisimo_Integrations_SEO_Suite::sitemap_query( apply_filters( 'wp_sitemaps_posts_query_args', $args, $type ), $type );
		// Los filtros externos pueden añadir exclusiones, pero no alterar el alcance ni la paginación.
		$args['post_type'] = $type;
		$args['post_status'] = 'publish';
		$args['posts_per_page'] = $limit;
		$args['paged'] = $page;
		$args['fields'] = 'ids';
		$args['orderby'] = 'ID';
		$args['order'] = 'ASC';
		$args['has_password'] = false;
		$args['no_found_rows'] = true;
		$args['suppress_filters'] = false;
		return $args;
	}

	private static function has_posts( $type ) {
		return (bool) get_posts( self::args( $type, 1, 1 ) );
	}

	/** En una instalación individual, sitemap.xml es un índice de archivos legibles. */
	public static function index_xml() {
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( self::filenames() as $type => $filename ) {
			if ( 'page' !== $type || 'page' === get_option( 'show_on_front' ) ) {
				if ( ! self::has_posts( $type ) ) continue;
			}
			echo '<sitemap><loc>' . esc_xml( self::endpoint( $filename ) ) . "</loc></sitemap>\n";
		}
		if ( self::news_enabled() ) echo '<sitemap><loc>' . esc_xml( self::endpoint( self::NEWS_FILE ) ) . "</loc></sitemap>\n";
		if ( self::video_enabled() ) echo '<sitemap><loc>' . esc_xml( self::endpoint( self::VIDEO_FILE ) ) . "</loc></sitemap>\n";
		echo "</sitemapindex>\n";
	}

	const MAX_IMAGES = 20;

	/** Hosts propios del sitio en curso: la portada y la biblioteca de medios (puede ser un CDN propio). */
	private static function own_hosts() {
		static $hosts = array();
		$blog = get_current_blog_id();
		if ( isset( $hosts[ $blog ] ) ) return $hosts[ $blog ];
		$uploads = wp_get_upload_dir();
		return $hosts[ $blog ] = array_values( array_unique( array_filter( array( wp_parse_url( home_url( '/' ), PHP_URL_HOST ), wp_parse_url( (string) ( $uploads['baseurl'] ?? '' ), PHP_URL_HOST ) ) ) ) );
	}

	/**
	 * URLs de imagen de un HTML que pertenecen a los hosts dados, sin repetir y en orden.
	 * Pura: la prueba la usa sin WordPress.
	 */
	public static function content_images( $html, array $hosts, $limit = self::MAX_IMAGES ) {
		$found = array();
		if ( '' === (string) $html || false === stripos( $html, '<img' ) ) return $found;
		preg_match_all( '/<img\b[^>]*?\ssrc\s*=\s*(["\'])(.*?)\1/is', (string) $html, $matches );
		foreach ( $matches[2] as $src ) {
			$src = html_entity_decode( trim( $src ), ENT_QUOTES );
			if ( 0 === strpos( $src, '//' ) ) $src = 'https:' . $src;
			if ( ! preg_match( '#^https?://#i', $src ) ) continue;
			if ( ! in_array( strtolower( (string) parse_url( $src, PHP_URL_HOST ) ), array_map( 'strtolower', $hosts ), true ) ) continue;
			$found[ $src ] = true;
			if ( count( $found ) >= $limit ) break;
		}
		return array_keys( $found );
	}

	/** Imagen destacada y del contenido de una entrada; respeta la exclusión por adjunto. */
	private static function post_images( $post_id ) {
		$images = array();
		$thumb  = (int) get_post_meta( $post_id, '_thumbnail_id', true );
		if ( $thumb && '1' !== get_post_meta( $thumb, 'digitalisimo_seo_sitemap_exclude', true ) ) { $src = wp_get_attachment_url( $thumb ); if ( $src ) $images[] = $src; }
		$post = get_post( $post_id );
		if ( $post && empty( $post->post_password ) ) $images = array_merge( $images, self::content_images( $post->post_content, self::own_hosts() ) );
		return array_slice( array_values( array_unique( $images ) ), 0, self::MAX_IMAGES );
	}

	/* ------------------------------------------------------------------ */
	/* Noticias (RM4 de docs/SEO_RANKMATH_OBJECTIVE.md).                    */
	/* ------------------------------------------------------------------ */

	public static function news_enabled() { return self::enabled() && (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_sitemap_news_enabled', 0 ); }

	/** Tipos de noticias: los elegidos que existen y se publican en el sitemap. */
	public static function news_types() {
		$types = array_filter( explode( ',', Digitalisimo_Integrations_SEO_Suite::sanitize_sitemap_list( Digitalisimo_Integrations_SEO_Resolver::option( 'seo_sitemap_news_types', 'post' ) ) ) );
		return array_values( array_intersect( $types ?: array( 'post' ), array_keys( Digitalisimo_Integrations_SEO_Suite::sitemap_posts( get_post_types( array( 'public' => true ), 'objects' ) ) ) ) );
	}

	/** Idioma en el formato de Google News: ISO 639 en minúsculas, salvo zh-cn y zh-tw. */
	public static function news_language( $locale ) {
		$locale = strtolower( str_replace( '_', '-', (string) $locale ) );
		if ( in_array( $locale, array( 'zh-cn', 'zh-tw' ), true ) ) return $locale;
		return substr( $locale, 0, 2 ) ?: 'es';
	}

	/** IDs de términos excluidos: una lista de números. */
	public static function sanitize_ids( $value ) {
		// intval y no absint: «-1» no puede convertirse en la categoría 1.
		$ids = array_unique( array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', is_scalar( $value ) ? (string) $value : '' ) ), function( $id ) { return $id > 0; } ) );
		return implode( ',', array_slice( $ids, 0, 200 ) );
	}

	/** Consulta de noticias: publicadas en las últimas 48 h, sin noindex ni exclusiones, como máximo 1 000. */
	public static function news_query( $types ) {
		$args = array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => self::NEWS_LIMIT, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true, 'has_password' => false, 'ignore_sticky_posts' => true, 'suppress_filters' => false, 'date_query' => array( array( 'column' => 'post_date_gmt', 'after' => gmdate( 'Y-m-d H:i:s', time() - self::NEWS_HOURS * HOUR_IN_SECONDS ), 'inclusive' => true ) ) );
		$args = Digitalisimo_Integrations_SEO_Suite::sitemap_query( $args, 'post' );
		$args['meta_query'][] = array( 'relation' => 'OR', array( 'key' => 'digitalisimo_seo_news_exclude', 'compare' => 'NOT EXISTS' ), array( 'key' => 'digitalisimo_seo_news_exclude', 'value' => '1', 'compare' => '!=' ) );
		$terms = array_filter( array_map( 'absint', explode( ',', (string) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_sitemap_news_exclude_terms', '' ) ) ) );
		if ( $terms ) $args['tax_query'] = array( array( 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $terms, 'operator' => 'NOT IN' ) );
		return $args;
	}

	public static function news_xml() {
		$name     = (string) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_sitemap_news_name', '' ) ?: ( (string) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_site_name', '' ) ?: get_bloginfo( 'name' ) );
		$language = self::news_language( get_locale() );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
		$types = self::news_types();
		foreach ( $types ? get_posts( self::news_query( $types ) ) : array() as $post_id ) {
			$url = get_permalink( $post_id );
			if ( ! $url ) continue;
			echo '<url><loc>' . esc_xml( $url ) . '</loc><news:news><news:publication><news:name>' . esc_xml( html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES, 'UTF-8' ) ) . '</news:name><news:language>' . esc_xml( $language ) . '</news:language></news:publication><news:publication_date>' . esc_xml( get_post_time( DATE_W3C, true, $post_id ) ) . '</news:publication_date><news:title>' . esc_xml( html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' ) ) . "</news:title></news:news></url>\n";
		}
		echo "</urlset>\n";
	}

	/* ------------------------------------------------------------------ */
	/* Vídeo: video-sitemap.xml a partir de la meta de cada contenido.      */
	/* ------------------------------------------------------------------ */

	public static function video_enabled() { return self::enabled() && (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_sitemap_video_enabled', 0 ); }

	/**
	 * Un <video:video> o '' si falta algo que el formato exige: miniatura, título, descripción y URL
	 * del archivo o del reproductor. Puro: recibe el vídeo ya resuelto.
	 */
	public static function video_entry( $video ) {
		if ( ! is_array( $video ) || empty( $video['thumbnailUrl'] ) || empty( $video['name'] ) || empty( $video['description'] ) || ( empty( $video['contentUrl'] ) && empty( $video['embedUrl'] ) ) ) return '';
		$text = function( $value, $max ) { $value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ); return esc_xml( function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max ) ); };
		$xml = '<video:video><video:thumbnail_loc>' . esc_xml( $video['thumbnailUrl'] ) . '</video:thumbnail_loc><video:title>' . $text( $video['name'], 100 ) . '</video:title><video:description>' . $text( $video['description'], 2048 ) . '</video:description>';
		$xml .= ! empty( $video['contentUrl'] ) ? '<video:content_loc>' . esc_xml( $video['contentUrl'] ) . '</video:content_loc>' : '<video:player_loc>' . esc_xml( $video['embedUrl'] ) . '</video:player_loc>';
		if ( ! empty( $video['duration'] ) && preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $video['duration'], $d ) ) {
			$seconds = (int) ( $d[1] ?? 0 ) * 3600 + (int) ( $d[2] ?? 0 ) * 60 + (int) ( $d[3] ?? 0 );
			if ( $seconds >= 1 && $seconds <= 28800 ) $xml .= '<video:duration>' . $seconds . '</video:duration>';
		}
		if ( ! empty( $video['uploadDate'] ) ) $xml .= '<video:publication_date>' . esc_xml( $video['uploadDate'] ) . '</video:publication_date>';
		return $xml . '</video:video>';
	}

	public static function video_xml() {
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
		$types = array_keys( Digitalisimo_Integrations_SEO_Suite::sitemap_posts( get_post_types( array( 'public' => true ), 'objects' ) ) );
		$left  = self::VIDEO_LIMIT;
		// Por tipo: cada uno conserva sus exclusiones (productos ocultos, páginas de WooCommerce, noindex).
		foreach ( $types as $type ) {
			if ( $left <= 0 ) break;
			$args = Digitalisimo_Integrations_SEO_Suite::sitemap_query( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => $left, 'fields' => 'ids', 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true, 'has_password' => false, 'ignore_sticky_posts' => true, 'suppress_filters' => false ), $type );
			$args['meta_query'][] = array( 'key' => Digitalisimo_Integrations_Video::META, 'compare' => 'EXISTS' );
			foreach ( get_posts( $args ) as $post_id ) {
				$entry = self::video_entry( get_post_meta( $post_id, Digitalisimo_Integrations_Video::META, true ) );
				$url   = get_permalink( $post_id );
				if ( '' === $entry || ! $url ) continue;
				echo '<url><loc>' . esc_xml( $url ) . '</loc>' . $entry . "</url>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- video_entry() escapa cada valor.
				$left--;
			}
		}
		echo "</urlset>\n";
	}

	/** Un contenido excluido de Google News sigue en la búsqueda normal: sólo Googlebot-News recibe noindex. */
	public static function news_robots() {
		if ( is_singular() && '1' === get_post_meta( get_queried_object_id(), 'digitalisimo_seo_news_exclude', true ) ) echo '<meta name="Googlebot-News" content="noindex">' . "\n";
	}

	/* ------------------------------------------------------------------ */
	/* Mapa del sitio en HTML: [digitalisimo_sitemap type="post,page"].    */
	/* ------------------------------------------------------------------ */

	const HTML_LIMIT = 500;

	public static function html_shortcode( $atts = array() ) {
		$atts    = shortcode_atts( array( 'type' => '' ), $atts, 'digitalisimo_sitemap' );
		$objects = Digitalisimo_Integrations_SEO_Suite::sitemap_posts( get_post_types( array( 'public' => true ), 'objects' ) );
		$wanted  = array_filter( explode( ',', Digitalisimo_Integrations_SEO_Suite::sanitize_sitemap_list( $atts['type'] ) ) );
		if ( $wanted ) $objects = array_intersect_key( $objects, array_flip( $wanted ) );
		$html = '';
		foreach ( $objects as $type => $object ) {
			$args = self::args( $type, 1, self::HTML_LIMIT );
			$args['orderby'] = 'title';
			$args['order']   = 'ASC';
			$ids = get_posts( $args );
			if ( ! $ids ) continue;
			$html .= '<section class="digitalisimo-html-sitemap__group"><h2>' . esc_html( $object->labels->name ?? $type ) . '</h2><ul>';
			foreach ( $ids as $id ) $html .= '<li><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $id ) ) ) . '</a></li>';
			$html .= '</ul></section>';
		}
		return '' === $html ? '' : '<nav class="digitalisimo-html-sitemap" aria-label="Mapa del sitio">' . $html . '</nav>';
	}

	/** En Multisite se emite un solo urlset; en un sitio individual, uno por tipo. */
	public static function urls_xml( $types ) {
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$images = (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_sitemap_images', 1 );
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . ( $images ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '' ) . '>' . "\n";
		$home = 'page' !== get_option( 'show_on_front' );
		if ( $home && ( is_multisite() || in_array( 'page', $types, true ) ) ) echo '<url><loc>' . esc_xml( home_url( '/' ) ) . "</loc></url>\n";
		foreach ( $types as $type ) {
			for ( $page = 1; ; ++$page ) {
				$posts = get_posts( self::args( $type, $page ) );
				// Un lote de consultas por página del sitemap, nunca una por URL.
				if ( $images && $posts && function_exists( '_prime_post_caches' ) ) { $ids = array_map( 'intval', $posts ); _prime_post_caches( $ids, false, true ); $thumbs = array_filter( array_map( function( $id ) { return (int) get_post_meta( $id, '_thumbnail_id', true ); }, $ids ) ); if ( $thumbs ) _prime_post_caches( $thumbs, false, true ); }
				foreach ( $posts as $post ) {
					$post_id = is_object( $post ) ? $post->ID : (int) $post;
					$url = get_permalink( $post_id ); // Nunca reconstruir ni cambiar el slug del contenido.
					if ( ! $url || ( $home && untrailingslashit( $url ) === untrailingslashit( home_url( '/' ) ) ) ) continue;
					$entry = apply_filters( 'wp_sitemaps_posts_entry', array( 'loc' => $url ), get_post( $post_id ), $type );
					if ( ! is_array( $entry ) || empty( $entry['loc'] ) ) continue;
					echo '<url><loc>' . esc_xml( $url ) . '</loc>';
					if ( $images ) foreach ( self::post_images( $post_id ) as $image ) echo '<image:image><image:loc>' . esc_xml( $image ) . '</image:loc></image:image>';
					echo "</url>\n";
				}
				if ( count( $posts ) < self::BATCH ) break;
			}
		}
		echo "</urlset>\n";
	}
}
