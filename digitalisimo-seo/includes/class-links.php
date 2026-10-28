<?php
defined( 'ABSPATH' ) || exit;

/**
 * Enlaces internos (RM7 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Una tabla por sitio con los enlaces de cada contenido publicado, rellenada al guardar y, para lo
 * existente, por lotes en cron. Sólo informa: recuentos en los listados, páginas huérfanas y
 * contenidos secundarios sin enlace a su pilar en Auditoría, y sugerencias en el editor. Nunca
 * inserta ni cambia un enlace del contenido.
 *
 * Con `seo_links_check` (opt-in) comprueba además, en cola de WP-cron y por lotes pequeños, el estado
 * HTTP de cada URL enlazada. Hace peticiones a sitios de terceros, por eso no viene encendido.
 */
class Digitalisimo_Integrations_Links {
	const DB_VERSION = 2;
	const DB_OPTION  = 'digitalisimo_seo_links_db';
	const META       = 'digitalisimo_seo_links_v';
	const VERSION    = 1;
	const HOOK       = 'digitalisimo_seo_links_batch';
	const BATCH      = 50;
	const MAX_LINKS  = 300;
	/** Sin pendientes: el escritorio deja de consultar el backlog en cada carga. */
	const DONE       = 'digitalisimo_seo_links_done';
	const CHECK_HOOK  = 'digitalisimo_seo_links_check';
	const CHECK_BATCH = 10;
	/** Una URL comprobada se vuelve a comprobar pasada una semana. */
	const RECHECK     = WEEK_IN_SECONDS;

	public static function table() { global $wpdb; return $wpdb->prefix . 'digitalisimo_links'; }
	public static function status_table() { global $wpdb; return $wpdb->prefix . 'digitalisimo_link_status'; }
	public static function check_enabled() { return self::enabled() && (bool) self::o( 'seo_links_check', 0 ); }
	private static function o( $key, $default = '' ) { return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default ); }
	public static function enabled() { return (bool) self::o( 'seo_links_counter', 1 ); }
	public static function installed() { return (int) get_option( self::DB_OPTION ) >= self::DB_VERSION; }

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'after_save' ), 20, 4 );
		add_action( 'before_delete_post', array( __CLASS__, 'forget' ) );
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_action( 'admin_post_digitalisimo_seo_links_rescan', array( __CLASS__, 'handle_rescan' ) );
		add_action( self::CHECK_HOOK, array( __CLASS__, 'run_check' ) );
		add_action( 'admin_post_digitalisimo_seo_links_check', array( __CLASS__, 'handle_check' ) );
	}

	public static function admin_init() {
		if ( ! self::enabled() ) return;
		if ( ! self::installed() ) self::install();
		foreach ( self::types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'column' ) );
			add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'column_value' ), 10, 2 );
		}
		if ( ! get_option( self::DONE ) && ! wp_next_scheduled( self::HOOK ) && self::backlog( 1 ) ) wp_schedule_single_event( time() + 30, self::HOOK );
		if ( self::check_enabled() && ! wp_next_scheduled( self::CHECK_HOOK ) ) wp_schedule_single_event( time() + 60, self::CHECK_HOOK );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( 'CREATE TABLE ' . self::table() . ' (
			id bigint(20) unsigned NOT NULL auto_increment,
			post_id bigint(20) unsigned NOT NULL,
			target_post_id bigint(20) unsigned NOT NULL default 0,
			url varchar(255) NOT NULL default \'\',
			type varchar(8) NOT NULL,
			PRIMARY KEY  (id),
			KEY post_type (post_id, type),
			KEY target_post_id (target_post_id),
			KEY url (url(191))
		) ' . $wpdb->get_charset_collate() . ';' );
		dbDelta( 'CREATE TABLE ' . self::status_table() . ' (
			url_hash char(40) NOT NULL,
			url varchar(255) NOT NULL,
			code smallint(5) unsigned NULL default NULL,
			error varchar(120) NOT NULL default \'\',
			checked datetime NULL default NULL,
			PRIMARY KEY  (url_hash),
			KEY checked (checked)
		) ' . $wpdb->get_charset_collate() . ';' );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/** Los mismos tipos que el sitemap: lo que el sitio publica para buscadores. */
	public static function types() { return array_keys( Digitalisimo_Integrations_SEO_Suite::sitemap_posts( get_post_types( array( 'public' => true ), 'objects' ) ) ); }

	/* ------------------------------------------------------------------ */
	/* Extracción (pura).                                                   */
	/* ------------------------------------------------------------------ */

	private static function host( $url ) { return preg_replace( '/^www\./', '', strtolower( (string) parse_url( $url, PHP_URL_HOST ) ) ); }

	/**
	 * Enlaces de un HTML como URLs absolutas, clasificados en internos y externos.
	 * Omite anclas, mailto:, tel:, javascript: y data:; una ruta que empieza por / es del sitio.
	 *
	 * @return array<int,array{url:string,internal:bool}>
	 */
	public static function extract( $html, $home ) {
		$hrefs = array();
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$tags = new WP_HTML_Tag_Processor( (string) $html );
			while ( $tags->next_tag( 'a' ) ) { $href = $tags->get_attribute( 'href' ); if ( is_string( $href ) ) $hrefs[] = $href; }
		} else {
			preg_match_all( '/<a\b[^>]*?\shref\s*=\s*(["\'])(.*?)\1/is', (string) $html, $m );
			$hrefs = array_map( function( $href ) { return html_entity_decode( $href, ENT_QUOTES ); }, $m[2] );
		}
		$home_host = self::host( $home );
		$scheme    = parse_url( $home, PHP_URL_SCHEME ) ?: 'https';
		$base      = $scheme . '://' . parse_url( $home, PHP_URL_HOST ) . ( parse_url( $home, PHP_URL_PORT ) ? ':' . parse_url( $home, PHP_URL_PORT ) : '' );
		$out = array();
		foreach ( $hrefs as $href ) {
			$href = trim( $href );
			if ( '' === $href || '#' === $href[0] || preg_match( '/^(mailto|tel|javascript|data|sms|whatsapp):/i', $href ) ) continue;
			if ( 0 === strpos( $href, '//' ) ) $href = $scheme . ':' . $href;
			elseif ( '/' === $href[0] ) $href = $base . $href;
			elseif ( ! preg_match( '#^https?://#i', $href ) ) continue;
			$out[] = array( 'url' => substr( $href, 0, 255 ), 'internal' => self::host( $href ) === $home_host );
			if ( count( $out ) >= self::MAX_LINKS ) break;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Registro.                                                            */
	/* ------------------------------------------------------------------ */

	public static function after_save( $post_id, $post, $update, $post_before ) {
		if ( ! self::enabled() || ! self::installed() || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
		self::process( $post_id );
	}

	/** Recalcula los enlaces de un contenido; uno que deja de estar publicado deja de contar. */
	public static function process( $post_id ) {
		global $wpdb;
		$post = get_post( $post_id );
		$wpdb->delete( self::table(), array( 'post_id' => (int) $post_id ) );
		if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, self::types(), true ) ) { delete_post_meta( $post_id, self::META ); return; }
		static $targets = array();
		$rows = array();
		foreach ( self::extract( $post->post_content, home_url( '/' ) ) as $link ) {
			$target = 0;
			if ( $link['internal'] ) {
				$clean = explode( '?', explode( '#', $link['url'] )[0] )[0];
				if ( ! isset( $targets[ $clean ] ) ) $targets[ $clean ] = (int) url_to_postid( $clean );
				$target = $targets[ $clean ];
			}
			$rows[] = $wpdb->prepare( '(%d,%d,%s,%s)', (int) $post_id, $target, $link['url'], $link['internal'] ? 'internal' : 'external' );
		}
		if ( $rows ) $wpdb->query( 'INSERT INTO ' . self::table() . ' (post_id, target_post_id, url, type) VALUES ' . implode( ',', $rows ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- cada fila viene de prepare().
		update_post_meta( $post_id, self::META, self::VERSION );
	}

	/** Un contenido borrado deja de enlazar y sus enlaces entrantes quedan rotos (destino 0). */
	public static function forget( $post_id ) {
		global $wpdb;
		if ( ! self::installed() ) return;
		$wpdb->delete( self::table(), array( 'post_id' => (int) $post_id ) );
		$wpdb->update( self::table(), array( 'target_post_id' => 0 ), array( 'target_post_id' => (int) $post_id ) );
	}

	/** Contenidos publicados que aún no se analizaron con la versión actual. */
	public static function backlog( $limit = self::BATCH ) {
		$types = self::types();
		if ( ! $types ) return array();
		return get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => $limit, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true, 'meta_query' => array( 'relation' => 'OR', array( 'key' => self::META, 'compare' => 'NOT EXISTS' ), array( 'key' => self::META, 'value' => self::VERSION, 'compare' => '!=' ) ) ) );
	}

	public static function run_batch() {
		if ( ! self::enabled() || ! self::installed() ) return;
		$started = time();
		foreach ( self::backlog() as $post_id ) { self::process( $post_id ); if ( time() - $started > 20 ) break; }
		if ( ! self::backlog( 1 ) ) { update_option( self::DONE, 1, false ); return; }
		if ( ! wp_next_scheduled( self::HOOK ) ) wp_schedule_single_event( time() + 30, self::HOOK );
	}

	public static function handle_rescan() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_links_rescan' ) ) wp_die( 'No autorizado.' );
		delete_post_meta_by_key( self::META );
		delete_option( self::DONE );
		wp_clear_scheduled_hook( self::HOOK );
		self::run_batch();
		wp_safe_redirect( Digitalisimo_Integrations_SEO_Audit_Hub::url( 'links' ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Enlaces rotos (en cola, opt-in).                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Estado de una respuesta: ok, broken (no existe), error (servidor o red) o blocked (el destino
	 * no deja comprobarlo: pide acceso, limita o rechaza robots). Sólo «broken» y «error» son avisos.
	 */
	public static function classify( $code ) {
		$code = (int) $code;
		if ( in_array( $code, array( 401, 403, 405, 406, 429, 451, 999 ), true ) ) return 'blocked'; // 999: código propio de redes que rechazan robots.
		if ( 0 === $code || $code >= 500 ) return 'error';
		if ( $code >= 400 ) return 'broken';
		return 'ok';
	}

	/** Añade las URLs nuevas a la cola y olvida las que ya no enlaza ningún contenido. */
	public static function sync_status() {
		global $wpdb;
		$links  = self::table();
		$status = self::status_table();
		$new = (array) $wpdb->get_col( "SELECT DISTINCT l.url FROM {$links} l LEFT JOIN {$status} s ON s.url = l.url WHERE s.url IS NULL LIMIT 1000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $new ) $wpdb->query( "INSERT IGNORE INTO {$status} (url_hash, url) VALUES " . implode( ',', array_map( function( $url ) use ( $wpdb ) { return $wpdb->prepare( '(%s,%s)', sha1( $url ), $url ); }, $new ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- cada fila viene de prepare().
		$wpdb->query( "DELETE FROM {$status} WHERE url NOT IN ( SELECT url FROM {$links} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Lo que toca comprobar: lo nunca comprobado primero y después lo más antiguo. */
	public static function check_queue( $limit = self::CHECK_BATCH ) {
		global $wpdb;
		$before = gmdate( 'Y-m-d H:i:s', time() - self::RECHECK );
		return (array) $wpdb->get_col( $wpdb->prepare( 'SELECT url FROM ' . self::status_table() . ' WHERE checked IS NULL OR checked < %s ORDER BY checked IS NOT NULL, checked ASC LIMIT %d', $before, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** HEAD y, si el servidor no lo admite, GET limitado; sigue hasta 3 redirecciones. */
	public static function probe( $url ) {
		$args = array( 'timeout' => 8, 'redirection' => 3, 'user-agent' => 'DIGITALISIMO link check; ' . home_url( '/' ), 'limit_response_size' => 1024 );
		$response = wp_safe_remote_head( $url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || in_array( $code, array( 403, 405, 501 ), true ) ) {
			$response = wp_safe_remote_get( $url, $args );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		}
		$error = is_wp_error( $response ) ? substr( wp_strip_all_tags( $response->get_error_message() ), 0, 120 ) : '';
		return array( 'code' => $code, 'error' => $error );
	}

	public static function run_check() {
		global $wpdb;
		if ( ! self::check_enabled() || ! self::installed() ) return;
		self::sync_status();
		$started = time();
		foreach ( self::check_queue() as $url ) {
			$result = self::probe( $url );
			$wpdb->update( self::status_table(), array( 'code' => $result['code'], 'error' => $result['error'], 'checked' => current_time( 'mysql', true ) ), array( 'url_hash' => sha1( $url ) ) );
			if ( time() - $started > 25 ) break;
		}
		wp_clear_scheduled_hook( self::CHECK_HOOK );
		// Con cola, el siguiente lote en un minuto; sin ella, se revisa de nuevo cada día.
		wp_schedule_single_event( time() + ( self::check_queue( 1 ) ? MINUTE_IN_SECONDS : DAY_IN_SECONDS ), self::CHECK_HOOK );
	}

	/** «Comprobar ahora»: adelanta el lote al próximo cron, nunca hace peticiones en esta petición. */
	public static function handle_check() {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_links_check' ) ) wp_die( 'No autorizado.' );
		if ( self::installed() ) $wpdb->query( 'UPDATE ' . self::status_table() . ' SET checked = NULL' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		wp_clear_scheduled_hook( self::CHECK_HOOK );
		if ( self::check_enabled() ) wp_schedule_single_event( time(), self::CHECK_HOOK );
		wp_safe_redirect( Digitalisimo_Integrations_SEO_Audit_Hub::url( 'links' ) );
		exit;
	}

	/**
	 * Enlaces con problema y los contenidos que los usan.
	 *
	 * @return array<int,array{url:string,code:int,error:string,checked:string,state:string,posts:int[]}>
	 */
	public static function broken( $limit = 200 ) {
		global $wpdb;
		if ( ! self::installed() ) return array();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT s.url, s.code, s.error, s.checked, GROUP_CONCAT(DISTINCT l.post_id) AS posts FROM ' . self::status_table() . ' s JOIN ' . self::table() . ' l ON l.url = s.url WHERE s.checked IS NOT NULL AND ( s.code = 0 OR s.code >= 400 ) GROUP BY s.url_hash, s.url, s.code, s.error, s.checked ORDER BY s.code = 0, s.code DESC LIMIT %d', $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$state = self::classify( $row['code'] );
			if ( 'ok' === $state ) continue;
			$out[] = array( 'url' => $row['url'], 'code' => (int) $row['code'], 'error' => (string) $row['error'], 'checked' => (string) $row['checked'], 'state' => $state, 'posts' => array_map( 'intval', explode( ',', (string) $row['posts'] ) ) );
		}
		return $out;
	}

	/** Comprobadas, pendientes y con problema, para el resumen de Auditoría. */
	public static function check_summary() {
		global $wpdb;
		if ( ! self::installed() ) return array( 'total' => 0, 'pending' => 0 );
		$total   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::status_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$pending = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::status_table() . ' WHERE checked IS NULL' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array( 'total' => $total, 'pending' => $pending );
	}

	/* ------------------------------------------------------------------ */
	/* Consultas.                                                           */
	/* ------------------------------------------------------------------ */

	/** Internos, externos y entrantes de varios contenidos en dos consultas. */
	public static function counts( array $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		$out = array_fill_keys( $ids, array( 'internal' => 0, 'external' => 0, 'incoming' => 0 ) );
		if ( ! $ids || ! self::installed() ) return $out;
		$in = implode( ',', $ids );
		foreach ( (array) $wpdb->get_results( 'SELECT post_id, type, COUNT(*) AS n FROM ' . self::table() . " WHERE post_id IN ($in) GROUP BY post_id, type", ARRAY_A ) as $row ) $out[ (int) $row['post_id'] ][ $row['type'] ] = (int) $row['n']; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs enteros.
		foreach ( (array) $wpdb->get_results( 'SELECT target_post_id, COUNT(DISTINCT post_id) AS n FROM ' . self::table() . " WHERE target_post_id IN ($in) AND target_post_id <> post_id GROUP BY target_post_id", ARRAY_A ) as $row ) $out[ (int) $row['target_post_id'] ]['incoming'] = (int) $row['n']; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $out;
	}

	/** Publicados e indexables sin ningún enlace entrante de otro contenido (sin la portada). */
	public static function orphans( $limit = 200 ) {
		global $wpdb;
		$types = self::types();
		if ( ! $types || ! self::installed() ) return array();
		$in    = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";
		$front = (int) get_option( 'page_on_front' );
		$sql   = "SELECT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = 'digitalisimo_seo_noindex'
			WHERE p.post_status = 'publish' AND p.post_type IN ($in) AND p.ID <> %d AND ( n.meta_value IS NULL OR n.meta_value <> '1' )
			AND NOT EXISTS ( SELECT 1 FROM " . self::table() . ' l WHERE l.target_post_id = p.ID AND l.post_id <> p.ID )
			ORDER BY p.post_date DESC LIMIT %d';
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, $front, $limit ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Contenidos secundarios publicados que no enlazan a su pilar. */
	public static function pillar_gaps( $limit = 200 ) {
		global $wpdb;
		if ( ! self::installed() ) return array();
		$sql = "SELECT m.post_id, CAST(m.meta_value AS UNSIGNED) AS pillar FROM {$wpdb->postmeta} m
			JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_status = 'publish'
			JOIN {$wpdb->posts} q ON q.ID = CAST(m.meta_value AS UNSIGNED) AND q.post_status = 'publish'
			WHERE m.meta_key = '_related_pillar' AND m.post_id <> CAST(m.meta_value AS UNSIGNED)
			AND NOT EXISTS ( SELECT 1 FROM " . self::table() . ' l WHERE l.post_id = m.post_id AND l.target_post_id = CAST(m.meta_value AS UNSIGNED) )
			LIMIT %d';
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Sugerencias para un contenido: su pilar si no lo enlaza y otros contenidos cuya keyword principal
	 * aparece en el texto y aún no se enlazan. Pura: recibe el texto, lo ya enlazado y las keywords.
	 *
	 * @param string $text     Texto visible del contenido.
	 * @param int[]  $linked   IDs ya enlazados.
	 * @param array  $keywords ID => keyword principal de otros contenidos.
	 * @return array<int,string> ID => keyword encontrada.
	 */
	public static function suggest( $text, array $linked, array $keywords, $limit = 8 ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text ) : strtolower( (string) $text );
		$out  = array();
		foreach ( $keywords as $id => $keyword ) {
			$keyword = trim( function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $keyword ) : strtolower( (string) $keyword ) );
			if ( in_array( (int) $id, $linked, true ) || ( function_exists( 'mb_strlen' ) ? mb_strlen( $keyword ) : strlen( $keyword ) ) < 4 ) continue;
			// Palabra completa: «web» no debe coincidir dentro de «webinar».
			if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $keyword, '/' ) . '(?![\p{L}\p{N}])/u', $text ) ) $out[ (int) $id ] = $keyword;
			if ( count( $out ) >= $limit ) break;
		}
		return $out;
	}

	private static function keyword_map( $exclude ) {
		global $wpdb;
		$types = self::types();
		if ( ! $types ) return array();
		$in   = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = 'digitalisimo_seo_keywords' AND m.meta_value <> '' AND p.post_status = 'publish' AND p.post_type IN ($in) AND p.ID <> %d ORDER BY p.post_date DESC LIMIT 1000", (int) $exclude ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$map  = array();
		foreach ( (array) $rows as $row ) { $first = trim( explode( ',', (string) $row['meta_value'] )[0] ); if ( '' !== $first ) $map[ (int) $row['post_id'] ] = $first; }
		return $map;
	}

	/* ------------------------------------------------------------------ */
	/* Administración.                                                      */
	/* ------------------------------------------------------------------ */

	public static function column( $columns ) { $columns['digitalisimo_links'] = 'Enlaces'; return $columns; }

	public static function column_value( $column, $post_id ) {
		static $counts = null;
		if ( 'digitalisimo_links' !== $column ) return;
		if ( null === $counts ) { global $wp_query; $counts = self::counts( wp_list_pluck( (array) ( $wp_query->posts ?? array() ), 'ID' ) ); }
		$c = $counts[ (int) $post_id ] ?? self::counts( array( $post_id ) )[ (int) $post_id ];
		if ( 'publish' !== get_post_status( $post_id ) ) { echo '—'; return; }
		echo '<span title="Internos · externos · entrantes">' . esc_html( $c['internal'] ) . ' int · ' . esc_html( $c['external'] ) . ' ext · ' . esc_html( $c['incoming'] ) . ' entrantes</span>';
	}

	public static function metabox() {
		if ( ! self::enabled() ) return;
		foreach ( self::types() as $type ) add_meta_box( 'digitalisimo_links', 'Enlaces internos', array( __CLASS__, 'metabox_html' ), $type, 'side', 'low' );
	}

	public static function metabox_html( $post ) {
		if ( 'publish' !== $post->post_status ) { echo '<p class="description">Se analizan al publicar.</p>'; return; }
		global $wpdb;
		$c = self::counts( array( $post->ID ) )[ $post->ID ];
		echo '<p>' . esc_html( $c['internal'] ) . ' internos · ' . esc_html( $c['external'] ) . ' externos · ' . esc_html( $c['incoming'] ) . ' entrantes</p>';
		$linked = self::installed() ? array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT target_post_id FROM ' . self::table() . ' WHERE post_id = %d AND target_post_id > 0', $post->ID ) ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items  = array();
		$pillar = absint( get_post_meta( $post->ID, '_related_pillar', true ) );
		if ( $pillar && $pillar !== (int) $post->ID && ! in_array( $pillar, $linked, true ) && 'publish' === get_post_status( $pillar ) ) $items[ $pillar ] = 'tu contenido pilar';
		foreach ( self::suggest( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), $linked, self::keyword_map( $post->ID ) ) as $id => $keyword ) if ( ! isset( $items[ $id ] ) ) $items[ $id ] = '«' . $keyword . '» aparece en el texto';
		if ( ! $items ) { echo '<p class="description">Sin sugerencias: no hay otros contenidos cuya keyword principal aparezca en el texto sin enlazar.</p>'; return; }
		echo '<p><strong>Podrías enlazar:</strong></p><ul style="margin:0">';
		foreach ( $items as $id => $why ) echo '<li><a href="' . esc_url( get_permalink( $id ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $id ) ) . '</a><br><span class="description">' . esc_html( $why ) . '</span></li>';
		echo '</ul><p class="description">Sólo son sugerencias: ningún enlace se inserta solo. Se actualizan al guardar.</p>';
	}

	public static function render_audit() {
		echo '<h2>Enlaces internos</h2>';
		if ( ! self::enabled() ) { echo '<p>' . Digitalisimo_Integrations_Quality_Audit::badge( 'NO APLICABLE', 'SIN ACTIVAR' ) . ' El análisis de enlaces está apagado en SEO → Indexación.</p>'; return; } // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! self::installed() ) self::install();
		$pending = count( self::backlog( 500 ) );
		echo '<p>Se analiza cada contenido al publicarlo o actualizarlo, y lo existente en segundo plano. Sólo informa: nada se cambia en el contenido.</p>';
		if ( $pending ) echo '<div class="notice notice-info inline"><p>Análisis en curso: faltan ' . esc_html( $pending >= 500 ? 'más de 500' : (string) $pending ) . ' contenidos. Los resultados de abajo aún pueden cambiar.</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_seo_links_rescan">';
		wp_nonce_field( 'digitalisimo_seo_links_rescan' );
		submit_button( 'Volver a analizar todo', 'secondary', 'submit', false );
		echo '</form>';
		$orphans = self::orphans();
		echo '<h3>Páginas huérfanas (' . esc_html( count( $orphans ) ) . ')</h3><p class="description">Publicadas e indexables, sin ningún enlace desde otro contenido del sitio. Los buscadores las descubren peor; enlázalas desde contenidos relacionados.</p>';
		if ( $orphans ) { echo '<table class="widefat striped"><tbody>'; foreach ( $orphans as $id ) echo '<tr><td><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></td><td>' . esc_html( get_post_type_object( get_post_type( $id ) )->labels->singular_name ?? '' ) . '</td><td><a href="' . esc_url( get_permalink( $id ) ) . '" target="_blank" rel="noopener">Ver</a></td></tr>'; echo '</tbody></table>'; }
		$gaps = self::pillar_gaps();
		echo '<h3>Secundarios sin enlace a su pilar (' . esc_html( count( $gaps ) ) . ')</h3><p class="description">Contenidos de un cluster que no enlazan a su contenido pilar.</p>';
		if ( $gaps ) { echo '<table class="widefat striped"><thead><tr><th>Contenido</th><th>Pilar</th></tr></thead><tbody>'; foreach ( $gaps as $gap ) echo '<tr><td><a href="' . esc_url( get_edit_post_link( (int) $gap['post_id'] ) ) . '">' . esc_html( get_the_title( (int) $gap['post_id'] ) ) . '</a></td><td>' . esc_html( get_the_title( (int) $gap['pillar'] ) ) . '</td></tr>'; echo '</tbody></table>'; }
		self::render_broken();
	}

	private static function render_broken() {
		echo '<h3>Enlaces rotos</h3>';
		if ( ! self::check_enabled() ) { echo '<p>' . Digitalisimo_Integrations_Quality_Audit::badge( 'NO APLICABLE', 'SIN ACTIVAR' ) . ' Activa «Comprobar enlaces rotos» en SEO → Indexación. Hace peticiones a los sitios enlazados desde este servidor.</p>'; return; } // phpcs:ignore WordPress.Security.EscapeOutput
		$summary = self::check_summary();
		echo '<p class="description">Se comprueban en segundo plano, ' . esc_html( self::CHECK_BATCH ) . ' por minuto, y de nuevo cada semana. ' . esc_html( sprintf( '%1$d URL registradas, %2$d sin comprobar todavía.', $summary['total'], $summary['pending'] ) ) . ' Sólo informa: corrige o retira el enlace en su contenido, o crea una redirección si la URL rota es de este sitio.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_seo_links_check">';
		wp_nonce_field( 'digitalisimo_seo_links_check' );
		submit_button( 'Comprobar todo de nuevo', 'secondary', 'submit', false );
		echo '</form>';
		$broken = self::broken();
		if ( ! $broken ) { echo '<p>' . Digitalisimo_Integrations_Quality_Audit::badge( 'OK', 'SIN ENLACES ROTOS' ) . ' Ninguno de los enlaces comprobados falla.</p>'; return; } // phpcs:ignore WordPress.Security.EscapeOutput
		$labels = array( 'broken' => 'No existe', 'error' => 'Error', 'blocked' => 'No verificable' );
		echo '<table class="widefat striped"><thead><tr><th>Enlace</th><th>Respuesta</th><th>En</th><th>Comprobado</th></tr></thead><tbody>';
		foreach ( $broken as $row ) {
			$where = array();
			foreach ( array_slice( $row['posts'], 0, 5 ) as $id ) $where[] = '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>';
			if ( count( $row['posts'] ) > 5 ) $where[] = esc_html( sprintf( 'y %d más', count( $row['posts'] ) - 5 ) );
			$own = 0 === strpos( $row['url'], home_url( '/' ) );
			echo '<tr><td><code>' . esc_html( $row['url'] ) . '</code>' . ( $own && 'broken' === $row['state'] ? '<br><a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-seo-redirections&source=' . rawurlencode( (string) wp_parse_url( $row['url'], PHP_URL_PATH ) ) ) ) . '">Crear redirección</a>' : '' ) . '</td><td>' . esc_html( $labels[ $row['state'] ] ) . ' · ' . esc_html( $row['code'] ? (string) $row['code'] : $row['error'] ) . '</td><td>' . implode( ', ', $where ) . '</td><td>' . esc_html( get_date_from_gmt( $row['checked'], 'Y-m-d H:i' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table><p class="description">«No verificable»: el sitio enlazado pide acceso o rechaza las comprobaciones automáticas; ábrelo a mano para confirmarlo.</p>';
	}
}
