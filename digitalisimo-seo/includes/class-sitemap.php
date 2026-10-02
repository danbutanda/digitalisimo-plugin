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

	/** En Multisite se emite un solo urlset; en un sitio individual, uno por tipo. */
	public static function urls_xml( $types ) {
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		$home = 'page' !== get_option( 'show_on_front' );
		if ( $home && ( is_multisite() || in_array( 'page', $types, true ) ) ) echo '<url><loc>' . esc_xml( home_url( '/' ) ) . "</loc></url>\n";
		foreach ( $types as $type ) {
			for ( $page = 1; ; ++$page ) {
				$posts = get_posts( self::args( $type, $page ) );
				foreach ( $posts as $post ) {
					$post_id = is_object( $post ) ? $post->ID : (int) $post;
					$url = get_permalink( $post_id ); // Nunca reconstruir ni cambiar el slug del contenido.
					if ( ! $url || ( $home && untrailingslashit( $url ) === untrailingslashit( home_url( '/' ) ) ) ) continue;
					$entry = apply_filters( 'wp_sitemaps_posts_entry', array( 'loc' => $url ), get_post( $post_id ), $type );
					if ( ! is_array( $entry ) || empty( $entry['loc'] ) ) continue;
					echo '<url><loc>' . esc_xml( $url ) . "</loc></url>\n";
				}
				if ( count( $posts ) < self::BATCH ) break;
			}
		}
		echo "</urlset>\n";
	}
}
