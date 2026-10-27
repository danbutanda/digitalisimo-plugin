<?php
defined( 'ABSPATH' ) || exit;

/** Sitemap del sitio actual; nunca mezcla entradas de otros blogs de la red. */
class Digitalisimo_Integrations_Sitemap {
	const BATCH = 250;

	public static function init() {
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 0 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), 99, 2 );
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
		return rtrim( $output ) . "\nSitemap: " . self::endpoint() . "\n";
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
