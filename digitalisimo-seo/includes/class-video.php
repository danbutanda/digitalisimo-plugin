<?php
defined( 'ABSPATH' ) || exit;

/**
 * Vídeos de los contenidos para el sitemap de vídeo y el VideoObject (RM4 y RM8 de
 * docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Con `seo_sitemap_video_enabled` (opt-in) cada contenido guarda en una meta su vídeo principal, el
 * mismo que publica Schema_Graph: uno de la biblioteca o uno de Vimeo. Los datos de Vimeo (fecha,
 * duración, descripción y miniatura) llegan de su oEmbed público en cola de WP-cron, nunca durante
 * una visita. YouTube no se marca: su oEmbed no da la fecha de publicación que Google exige, y no
 * se inventa; sólo se cuenta para informarlo.
 */
class Digitalisimo_Integrations_Video {
	const META     = 'digitalisimo_seo_video';
	const VIMEO    = 'digitalisimo_seo_vimeo';
	const YOUTUBE  = 'digitalisimo_seo_video_youtube';
	const PENDING  = 'digitalisimo_seo_video_pending';
	const VERSION  = 1;
	const VERSION_META = 'digitalisimo_seo_video_v';
	const HOOK     = 'digitalisimo_seo_video_batch';
	const BATCH    = 50;
	const REMOTE   = 5;
	/** Un vídeo de Vimeo sin datos (privado o borrado) se vuelve a pedir pasada una semana. */
	const RETRY    = WEEK_IN_SECONDS;

	public static function init() {
		add_action( 'wp_after_insert_post', array( __CLASS__, 'after_save' ), 30, 4 );
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	public static function enabled() { return Digitalisimo_Integrations_Sitemap::enabled() && (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_sitemap_video_enabled', 0 ); }

	public static function admin_init() {
		if ( self::enabled() && ! wp_next_scheduled( self::HOOK ) && ( self::backlog( 1 ) || self::pending( 1 ) ) ) wp_schedule_single_event( time() + 30, self::HOOK );
	}

	/* ------------------------------------------------------------------ */
	/* Detección y formato (puros).                                         */
	/* ------------------------------------------------------------------ */

	/** Segundos a duración ISO 8601 (PT1H2M3S). */
	public static function iso_duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		if ( ! $seconds ) return '';
		$h = intdiv( $seconds, 3600 ); $m = intdiv( $seconds % 3600, 60 ); $s = $seconds % 60;
		return 'PT' . ( $h ? $h . 'H' : '' ) . ( $m ? $m . 'M' : '' ) . ( $s ? $s . 'S' : '' );
	}

	/**
	 * IDs de vídeos en un texto: URLs de Vimeo o YouTube en un iframe, un bloque embed, una línea
	 * suelta (oEmbed) o los ajustes de un widget.
	 *
	 * @return array{vimeo:string[],youtube:string[]}
	 */
	public static function find_ids( $text ) {
		$text = str_replace( '\\/', '/', (string) $text ); // JSON de Elementor.
		preg_match_all( '#(?:player\.)?vimeo\.com/(?:video/|channels/[\w-]+/|groups/[\w-]+/videos/)?(\d{6,12})\b#i', $text, $vimeo );
		preg_match_all( '#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^"\'\s<]*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})#i', $text, $youtube );
		return array( 'vimeo' => array_values( array_unique( $vimeo[1] ) ), 'youtube' => array_values( array_unique( $youtube[1] ) ) );
	}

	private static function post_text( $post ) {
		$elementor = get_post_meta( $post->ID, '_elementor_data', true );
		return (string) $post->post_content . "\n" . ( is_string( $elementor ) ? $elementor : '' );
	}

	/** IDs de Vimeo de un contenido (para el candidato de Schema_Graph). */
	public static function vimeo_ids( $post ) { return $post ? self::find_ids( self::post_text( $post ) )['vimeo'] : array(); }

	/**
	 * Datos útiles de un oEmbed de Vimeo, o null si falta algo que Google exige (título, miniatura y
	 * fecha). La fecha llega sin zona horaria: se publica sólo el día.
	 */
	public static function normalize_vimeo( $data, $id ) {
		if ( ! is_array( $data ) || (string) ( $data['video_id'] ?? $id ) !== (string) $id ) return null;
		$name  = trim( wp_strip_all_tags( (string) ( $data['title'] ?? '' ) ) );
		$thumb = (string) ( $data['thumbnail_url'] ?? '' );
		$date  = preg_match( '/^(\d{4}-\d{2}-\d{2})/', (string) ( $data['upload_date'] ?? '' ), $m ) ? $m[1] : '';
		if ( '' === $name || ! preg_match( '#^https://#i', $thumb ) || '' === $date ) return null;
		return array_filter( array(
			'name'         => $name,
			'description'  => trim( wp_strip_all_tags( (string) ( $data['description'] ?? '' ) ) ),
			'thumbnailUrl' => $thumb,
			'uploadDate'   => $date,
			'duration'     => self::iso_duration( (int) ( $data['duration'] ?? 0 ) ),
			'embedUrl'     => 'https://player.vimeo.com/video/' . $id,
		) );
	}

	/** Vídeo de Vimeo ya resuelto para un contenido, o null mientras no haya datos válidos. */
	public static function vimeo_video( $post_id, $id ) {
		$cache = get_post_meta( $post_id, self::VIMEO, true );
		$video = is_array( $cache ) ? ( $cache[ $id ]['video'] ?? null ) : null;
		return is_array( $video ) ? $video : null;
	}

	/* ------------------------------------------------------------------ */
	/* Registro y cola.                                                     */
	/* ------------------------------------------------------------------ */

	public static function after_save( $post_id, $post, $update, $post_before ) {
		if ( ! self::enabled() || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
		self::refresh( $post_id );
		if ( get_post_meta( $post_id, self::PENDING, true ) && ! wp_next_scheduled( self::HOOK ) ) wp_schedule_single_event( time() + 30, self::HOOK );
	}

	/** Recalcula el vídeo de un contenido sin peticiones; marca pendiente lo que falta de Vimeo. */
	public static function refresh( $post_id ) {
		$post = get_post( $post_id );
		$types = array_keys( Digitalisimo_Integrations_SEO_Suite::sitemap_posts( get_post_types( array( 'public' => true ), 'objects' ) ) );
		if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, $types, true ) ) {
			foreach ( array( self::META, self::PENDING, self::YOUTUBE ) as $key ) delete_post_meta( $post_id, $key );
			update_post_meta( $post_id, self::VERSION_META, self::VERSION );
			return;
		}
		$ids   = self::find_ids( self::post_text( $post ) );
		$cache = get_post_meta( $post_id, self::VIMEO, true );
		$cache = is_array( $cache ) ? array_intersect_key( $cache, array_flip( $ids['vimeo'] ) ) : array();
		$missing = array_filter( $ids['vimeo'], function( $id ) use ( $cache ) { return ! isset( $cache[ $id ] ) || ( empty( $cache[ $id ]['video'] ) && (int) ( $cache[ $id ]['checked'] ?? 0 ) < time() - self::RETRY ); } );
		$cache ? update_post_meta( $post_id, self::VIMEO, $cache ) : delete_post_meta( $post_id, self::VIMEO );
		$missing ? update_post_meta( $post_id, self::PENDING, 1 ) : delete_post_meta( $post_id, self::PENDING );
		$ids['youtube'] ? update_post_meta( $post_id, self::YOUTUBE, count( $ids['youtube'] ) ) : delete_post_meta( $post_id, self::YOUTUBE );
		$video = Digitalisimo_Integrations_Schema_Graph::video_for_post( $post );
		$video ? update_post_meta( $post_id, self::META, $video ) : delete_post_meta( $post_id, self::META );
		update_post_meta( $post_id, self::VERSION_META, self::VERSION );
	}

	/** Pide a Vimeo los datos que faltan de un contenido y lo recalcula. */
	public static function resolve( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) return;
		$cache = get_post_meta( $post_id, self::VIMEO, true );
		$cache = is_array( $cache ) ? $cache : array();
		foreach ( self::vimeo_ids( $post ) as $id ) {
			if ( ! empty( $cache[ $id ]['video'] ) || ( isset( $cache[ $id ] ) && (int) $cache[ $id ]['checked'] >= time() - self::RETRY ) ) continue;
			$response = wp_safe_remote_get( 'https://vimeo.com/api/oembed.json?url=' . rawurlencode( 'https://vimeo.com/' . $id ), array( 'timeout' => 8, 'limit_response_size' => 65536, 'user-agent' => 'DIGITALISIMO SEO; ' . home_url( '/' ) ) );
			$data = is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$cache[ $id ] = array( 'video' => self::normalize_vimeo( $data, $id ), 'checked' => time() );
		}
		update_post_meta( $post_id, self::VIMEO, $cache );
		self::refresh( $post_id );
	}

	/** Contenidos aún sin analizar con la versión actual. */
	public static function backlog( $limit = self::BATCH ) {
		$types = array_keys( Digitalisimo_Integrations_SEO_Suite::sitemap_posts( get_post_types( array( 'public' => true ), 'objects' ) ) );
		if ( ! $types ) return array();
		return get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => $limit, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true, 'meta_query' => array( 'relation' => 'OR', array( 'key' => self::VERSION_META, 'compare' => 'NOT EXISTS' ), array( 'key' => self::VERSION_META, 'value' => self::VERSION, 'compare' => '!=' ) ) ) );
	}

	public static function pending( $limit = self::REMOTE ) {
		return get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => $limit, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true, 'meta_key' => self::PENDING, 'meta_value' => '1' ) );
	}

	public static function run_batch() {
		if ( ! self::enabled() ) return;
		$started = time();
		foreach ( self::backlog() as $post_id ) { self::refresh( $post_id ); if ( time() - $started > 15 ) break; }
		foreach ( self::pending() as $post_id ) { self::resolve( $post_id ); if ( time() - $started > 25 ) break; }
		if ( ( self::backlog( 1 ) || self::pending( 1 ) ) && ! wp_next_scheduled( self::HOOK ) ) wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK );
	}

	/** Recuento para la pestaña Sitemap: con vídeo marcado, pendientes de Vimeo y con YouTube sin marcar. */
	public static function summary() {
		global $wpdb;
		$count = function( $key ) use ( $wpdb ) { return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT m.post_id) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_status = 'publish' WHERE m.meta_key = %s", $key ) ); };
		return array( 'videos' => $count( self::META ), 'pending' => $count( self::PENDING ), 'youtube' => $count( self::YOUTUBE ) );
	}
}
