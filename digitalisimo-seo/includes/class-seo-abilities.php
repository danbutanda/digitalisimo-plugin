<?php
defined( 'ABSPATH' ) || exit;

/**
 * Abilities de SEO para agentes de IA (RM15 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Sólo lectura: ninguna ability cambia contenido ni ajustes. Cada una comprueba permisos por objeto
 * (`edit_post` del contenido o `manage_options` para lo del sitio) y nunca devuelve secretos ni
 * códigos de verificación. Sin la Abilities API de WordPress (6.9) no se registra nada.
 */
class Digitalisimo_Integrations_SEO_Abilities {
	const CATEGORY = 'digitalisimo-seo';

	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) return;
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register' ) );
	}

	public static function register_category() {
		wp_register_ability_category( self::CATEGORY, array( 'label' => 'DIGITALÍSIMO SEO', 'description' => 'Lectura de los datos SEO del sitio: metadatos, análisis, schema, enlaces, redirecciones, 404 y estado de los archivos públicos.' ) );
	}

	private static function post_input() { return array( 'type' => 'object', 'required' => array( 'post_id' ), 'properties' => array( 'post_id' => array( 'type' => 'integer', 'description' => 'ID del contenido.' ) ), 'additionalProperties' => false ); }
	private static function search_input() { return array( 'type' => 'object', 'default' => array(), 'properties' => array( 'search' => array( 'type' => 'string', 'description' => 'Texto a buscar (opcional).' ), 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'description' => 'Máximo de filas (100 por defecto).' ) ), 'additionalProperties' => false ); }

	/** Nombre => array( etiqueta, descripción, entrada, ejecutor, permiso ). Pura: la prueba la recorre sin registrar. */
	public static function definitions() {
		$post = array( __CLASS__, 'can_edit' );
		$site = array( __CLASS__, 'can_manage' );
		return array(
			'get-post-seo'     => array( 'Metadatos SEO de un contenido', 'Título y descripción efectivos (los que publica la página), canonical, robots, keywords, datos sociales, título de miga, categoría principal, tipo de schema y puntuación del análisis.', self::post_input(), array( __CLASS__, 'get_post_seo' ), $post ),
			'analyze-post'     => array( 'Análisis SEO de un contenido', 'Comprobaciones del análisis de contenido (keyword, longitudes, enlaces, párrafos, medios) con su estado y su explicación, y la puntuación. Es una guía de redacción, no una garantía de posicionamiento.', self::post_input(), array( __CLASS__, 'analyze_post' ), $post ),
			'get-post-schema'  => array( 'Schema de un contenido', 'El @graph JSON-LD propio que publica el contenido.', self::post_input(), array( __CLASS__, 'get_post_schema' ), $post ),
			'get-post-links'   => array( 'Enlaces de un contenido', 'Recuentos de enlaces internos, externos y entrantes, y las páginas internas a las que enlaza.', self::post_input(), array( __CLASS__, 'get_post_links' ), $post ),
			'get-link-report'  => array( 'Informe de enlaces internos', 'Páginas huérfanas (sin enlaces entrantes) y contenidos secundarios que no enlazan a su pilar.', array( 'type' => 'object', 'default' => array(), 'properties' => array(), 'additionalProperties' => false ), array( __CLASS__, 'get_link_report' ), $site ),
			'get-redirections' => array( 'Redirecciones', 'Reglas de redirección del sitio con su origen, destino, código, estado y visitas.', self::search_input(), array( __CLASS__, 'get_redirections' ), $site ),
			'get-404-log'      => array( 'Registro de 404', 'URLs que respondieron 404 con sus visitas y la última procedencia, de la más visitada a la menos.', self::search_input(), array( __CLASS__, 'get_404_log' ), $site ),
			'get-site-status'  => array( 'Estado SEO del sitio', 'Sitemaps, robots.txt, llms.txt, buscadores verificados (sin sus códigos), redirecciones activas, registro de 404 y estado del importador de Rank Math.', array( 'type' => 'object', 'default' => array(), 'properties' => array(), 'additionalProperties' => false ), array( __CLASS__, 'get_site_status' ), $site ),
		);
	}

	public static function register() {
		foreach ( self::definitions() as $name => $d ) {
			wp_register_ability( self::CATEGORY . '/' . $name, array(
				'label'               => $d[0],
				'description'         => $d[1],
				'category'            => self::CATEGORY,
				'input_schema'        => $d[2],
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => $d[3],
				'permission_callback' => $d[4],
				'meta'                => array( 'show_in_rest' => true, 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'mcp' => array( 'public' => true ) ),
			) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Permisos.                                                            */
	/* ------------------------------------------------------------------ */

	public static function can_manage() { return current_user_can( 'manage_options' ); }
	public static function can_edit( $input = array() ) { $id = absint( is_array( $input ) ? ( $input['post_id'] ?? 0 ) : 0 ); return $id && current_user_can( 'edit_post', $id ); }

	private static function post( $input ) {
		$post = get_post( absint( $input['post_id'] ?? 0 ) );
		return $post && in_array( $post->post_status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) && in_array( $post->post_type, Digitalisimo_Integrations_Editorial::content_types(), true ) ? $post : null;
	}
	private static function missing() { return new WP_Error( 'digitalisimo_seo_not_found', 'No hay un contenido con SEO con ese ID.' ); }

	/* ------------------------------------------------------------------ */
	/* Ejecutores.                                                          */
	/* ------------------------------------------------------------------ */

	public static function get_post_seo( $input ) {
		$post = self::post( $input );
		if ( ! $post ) return self::missing();
		$m       = function( $key ) use ( $post ) { return (string) get_post_meta( $post->ID, 'digitalisimo_seo_' . $key, true ); };
		$snippet = Digitalisimo_Integrations_SEO_Suite::snippet_for( $post );
		$taxonomy = Digitalisimo_Integrations_Breadcrumbs::taxonomy_for( $post->post_type );
		$primary  = $taxonomy ? Digitalisimo_Integrations_SEO_Variables::primary_term( $post->ID, $taxonomy ) : null;
		$score    = get_post_meta( $post->ID, Digitalisimo_Integrations_Content_Analysis::SCORE, true );
		return array(
			'post_id'          => $post->ID,
			'url'              => get_permalink( $post ),
			'status'           => $post->post_status,
			'title'            => $snippet['title'],
			'description'      => $snippet['description'],
			'canonical'        => $m( 'canonical' ) ?: get_permalink( $post ),
			'robots'           => array_keys( array_filter( array( 'noindex' => '1' === $m( 'noindex' ), 'nofollow' => '1' === $m( 'nofollow' ), 'noarchive' => '1' === $m( 'noarchive' ), 'noimageindex' => '1' === $m( 'noimageindex' ), 'nosnippet' => '1' === $m( 'nosnippet' ) ) ) ),
			'keywords'         => array_values( array_filter( array_map( 'trim', explode( ',', $m( 'keywords' ) ) ) ) ),
			'social'           => array( 'title' => $m( 'social_title' ), 'description' => $m( 'social_description' ), 'image' => $m( 'social_image' ) ),
			'breadcrumb_title' => $m( 'breadcrumb_title' ),
			'primary_term'     => $primary ? array( 'taxonomy' => $taxonomy, 'id' => (int) $primary->term_id, 'name' => $primary->name ) : null,
			'schema_type'      => Digitalisimo_Integrations_Schema_Graph::chosen_type( $post->ID ) ?: Digitalisimo_Integrations_Schema_Graph::entity_type( $post ),
			'score'            => '' === $score ? null : (int) $score,
		);
	}

	public static function analyze_post( $input ) {
		$post = self::post( $input );
		if ( ! $post ) return self::missing();
		$result = Digitalisimo_Integrations_Content_Analysis::analyze( Digitalisimo_Integrations_Content_Analysis::data_for( $post ) );
		return array( 'post_id' => $post->ID, 'keyword' => Digitalisimo_Integrations_Content_Analysis::primary_keyword( $post->ID ), 'score' => $result['score'], 'checks' => $result['checks'] );
	}

	public static function get_post_schema( $input ) {
		$post = self::post( $input );
		if ( ! $post ) return self::missing();
		list( $graph ) = Digitalisimo_Integrations_Schema_Graph::graph_for( Digitalisimo_Integrations_Schema_Graph::site(), Digitalisimo_Integrations_Schema_Graph::post_page( $post ), $post->ID );
		return array( 'post_id' => $post->ID, 'graph' => $graph );
	}

	public static function get_post_links( $input ) {
		global $wpdb;
		$post = self::post( $input );
		if ( ! $post ) return self::missing();
		$counts  = Digitalisimo_Integrations_Links::counts( array( $post->ID ) )[ $post->ID ];
		$targets = Digitalisimo_Integrations_Links::installed() ? array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT target_post_id FROM ' . Digitalisimo_Integrations_Links::table() . ' WHERE post_id = %d AND target_post_id > 0', $post->ID ) ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array( 'post_id' => $post->ID, 'counts' => $counts, 'links_to' => array_map( function( $id ) { return array( 'id' => $id, 'title' => get_the_title( $id ), 'url' => get_permalink( $id ) ); }, $targets ) );
	}

	public static function get_link_report() {
		$item = function( $id ) { return array( 'id' => (int) $id, 'title' => get_the_title( (int) $id ), 'url' => get_permalink( (int) $id ) ); };
		return array(
			'orphans'     => array_map( $item, Digitalisimo_Integrations_Links::orphans() ),
			'pillar_gaps' => array_map( function( $gap ) use ( $item ) { return array( 'post' => $item( $gap['post_id'] ), 'pillar' => $item( $gap['pillar'] ) ); }, Digitalisimo_Integrations_Links::pillar_gaps() ),
			'pending'     => count( Digitalisimo_Integrations_Links::backlog( 500 ) ),
		);
	}

	private static function limit( $input ) { return min( 200, max( 1, absint( $input['limit'] ?? 100 ) ) ); }

	public static function get_redirections( $input = array() ) {
		global $wpdb;
		if ( (int) get_option( Digitalisimo_Integrations_Redirections::DB_OPTION ) < 1 ) return array( 'redirections' => array() );
		$search = sanitize_text_field( (string) ( $input['search'] ?? '' ) );
		$where  = "status IN ('active','inactive')" . ( '' !== $search ? $wpdb->prepare( ' AND ( sources LIKE %s OR url_to LIKE %s )', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%' ) : '' );
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT id, sources, url_to, header_code, status, hits, last_accessed FROM ' . Digitalisimo_Integrations_Redirections::table() . " WHERE $where ORDER BY updated DESC LIMIT %d", self::limit( $input ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as &$row ) { $row['sources'] = json_decode( (string) $row['sources'], true ) ?: array(); $row['id'] = (int) $row['id']; $row['header_code'] = (int) $row['header_code']; $row['hits'] = (int) $row['hits']; }
		return array( 'redirections' => (array) $rows );
	}

	public static function get_404_log( $input = array() ) {
		global $wpdb;
		if ( (int) get_option( Digitalisimo_Integrations_404_Monitor::DB_OPTION ) < 1 ) return array( 'enabled' => (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_404_enabled', 0 ), 'urls' => array() );
		$search = sanitize_text_field( (string) ( $input['search'] ?? '' ) );
		$where  = '' !== $search ? $wpdb->prepare( 'WHERE uri LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' ) : '';
		// Sin user agent: no hace falta para decidir una redirección.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT uri, hits, first_seen, last_seen, referer FROM ' . Digitalisimo_Integrations_404_Monitor::table() . " $where ORDER BY hits DESC, last_seen DESC LIMIT %d", self::limit( $input ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array( 'enabled' => (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_404_enabled', 0 ), 'urls' => array_map( function( $r ) { $r['hits'] = (int) $r['hits']; return $r; }, (array) $rows ) );
	}

	public static function get_site_status() {
		$job = Digitalisimo_Integrations_RankMath_Import::job();
		return array(
			'home'          => home_url( '/' ),
			'sitemap'       => array( 'enabled' => Digitalisimo_Integrations_Sitemap::enabled(), 'url' => Digitalisimo_Integrations_Sitemap::endpoint(), 'images' => (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_sitemap_images', 1 ), 'news' => Digitalisimo_Integrations_Sitemap::news_enabled() ? Digitalisimo_Integrations_Sitemap::endpoint( Digitalisimo_Integrations_Sitemap::NEWS_FILE ) : null ),
			'robots_txt'    => Digitalisimo_Integrations_Robots::preview(),
			'llms_txt'      => (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_ai_llms_enabled', 0 ),
			'verified_with' => array_values( array_map( function( $engine ) { return $engine[0]; }, array_filter( Digitalisimo_Integrations_Verification::engines(), function( $engine, $key ) { return '' !== Digitalisimo_Integrations_Verification::sanitize_code( Digitalisimo_Integrations_SEO_Resolver::option( $key, '' ) ); }, ARRAY_FILTER_USE_BOTH ) ) ),
			'redirections'  => (int) get_option( Digitalisimo_Integrations_Redirections::FLAG, 0 ),
			'log_404'       => (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_404_enabled', 0 ),
			'rankmath'      => $job ? array( 'status' => $job['status'] ?? '', 'done' => $job['done'] ?? array(), 'notes' => array_map( function( $n ) { return (int) ( $n['count'] ?? 0 ); }, (array) ( $job['notes'] ?? array() ) ) ) : null,
		);
	}
}
