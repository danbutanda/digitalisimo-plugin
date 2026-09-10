<?php
defined( 'ABSPATH' ) || exit;

/** Base de conocimiento local para recuperación contextual (RAG) de Digitalisimo AI. */
class Digitalisimo_AI_Knowledge {
	const OPTION = 'digitalisimo_ai_knowledge';
	const META = '_digitalisimo_ai_knowledge';

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'index_post' ), 90, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'remove_post' ) );
		add_action( 'digitalisimo_ai_knowledge_rebuild', array( __CLASS__, 'rebuild' ) );
		add_filter( 'digitalisimo_ai_context', array( __CLASS__, 'append_context' ), 20, 3 );
		add_action( 'admin_post_digitalisimo_ai_knowledge_rebuild', array( __CLASS__, 'request_rebuild' ) );
		add_action( 'admin_post_digitalisimo_ai_knowledge_embeddings', array( __CLASS__, 'request_embeddings' ) );
	}

	private static function enabled() { return (bool) Digitalisimo_AI::get( 'knowledge_enabled' ); }
	private static function index() { return (array) get_option( self::OPTION, array() ); }
	private static function save( $index ) { update_option( self::OPTION, $index, false ); }
	private static function allowed( $post ) {
		$robots = (array) get_post_meta( $post->ID, 'digitalisimo_seo_robots', true );
		return $post instanceof WP_Post && 'publish' === $post->post_status && post_type_supports( $post->post_type, 'editor' ) && ! in_array( 'noindex', $robots, true );
	}
	private static function tokens( $text ) {
		$text = remove_accents( strtolower( wp_strip_all_tags( (string) $text ) ) );
		$words = preg_split( '/[^a-z0-9]+/', $text );
		return array_values( array_unique( array_filter( $words, function( $word ) { return strlen( $word ) > 2; } ) ) );
	}
	private static function record( $post ) {
		$content = trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
		if ( ! $content ) return array();
		$headings = array();
		if ( preg_match_all( '/<h[1-3][^>]*>(.*?)<\/h[1-3]>/is', $post->post_content, $matches ) ) $headings = array_map( 'wp_strip_all_tags', $matches[1] );
		$seo_title = get_post_meta( $post->ID, 'digitalisimo_seo_title', true );
		$keywords = get_post_meta( $post->ID, 'digitalisimo_seo_keywords', true );
		$text = trim( $post->post_title . "\n" . $seo_title . "\n" . $keywords . "\n" . implode( "\n", $headings ) . "\n" . $content );
		return array(
			'id' => (int) $post->ID,
			'title' => $post->post_title,
			'url' => get_permalink( $post ),
			'type' => $post->post_type,
			'modified' => get_post_modified_time( 'c', true, $post ),
			'headings' => array_slice( $headings, 0, 12 ),
			'content' => wp_trim_words( $text, 420, '' ),
			'tokens' => self::tokens( $text ),
		);
	}

	public static function index_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || ! self::enabled() || ! Digitalisimo_AI::get( 'knowledge_auto_index' ) ) return;
		$index = self::index();
		if ( ! self::allowed( $post ) ) { unset( $index[ $post_id ] ); self::save( $index ); return; }
		$record = self::record( $post );
		if ( $record ) { $index[ $post_id ] = $record; self::save( $index ); update_post_meta( $post_id, self::META, $record['modified'] ); }
	}

	public static function remove_post( $post_id ) { $index = self::index(); unset( $index[ $post_id ] ); self::save( $index ); }
	public static function rebuild() {
		if ( ! self::enabled() ) return;
		$index = array();
		$posts = get_posts( array( 'post_type' => get_post_types( array( 'public' => true ), 'names' ), 'post_status' => 'publish', 'posts_per_page' => -1 ) );
		foreach ( $posts as $post ) if ( self::allowed( $post ) ) { $record = self::record( $post ); if ( $record ) $index[ $post->ID ] = $record; }
		self::save( $index );
		update_option( 'digitalisimo_ai_knowledge_rebuilt', time(), false );
	}
	public static function request_rebuild() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_ai_knowledge_rebuild' ) ) wp_die( esc_html__( 'Solicitud no autorizada.', 'digitalisimo-chatbot' ) );
		self::rebuild();
		wp_safe_redirect( add_query_arg( array( 'page' => 'digitalisimo-ai', 'tab' => 'knowledge', 'rebuilt' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
	/** Generación manual y opt-in: nunca se activa al guardar contenido. */
	public static function request_embeddings() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_ai_knowledge_embeddings' ) ) wp_die( esc_html__( 'Solicitud no autorizada.', 'digitalisimo-chatbot' ) );
		$result = self::generate_embeddings(); update_option( 'digitalisimo_ai_knowledge_embeddings_result', $result, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'digitalisimo-ai', 'tab' => 'knowledge', 'embeddings' => 1 ), admin_url( 'admin.php' ) ) ); exit;
	}
	public static function generate_embeddings() {
		if ( ! Digitalisimo_AI::get( 'knowledge_embeddings_enabled' ) ) return array( 'ok' => false, 'message' => 'Activa embeddings antes de generarlos.' );
		$providers = (array) Digitalisimo_AI::get( 'providers' ); $openai = (array) ( $providers['openai'] ?? array() ); if ( empty( $openai['enabled'] ) || empty( $openai['key'] ) ) return array( 'ok' => false, 'message' => 'Configura y activa OpenAI para embeddings.' );
		$index = self::index(); $ids = array_keys( $index ); if ( ! $ids ) return array( 'ok' => false, 'message' => 'No hay fuentes indexadas.' ); $ids = array_slice( $ids, 0, 20 ); $input = array(); foreach ( $ids as $id ) $input[] = wp_trim_words( ( $index[ $id ]['title'] ?? '' ) . "\n" . ( $index[ $id ]['content'] ?? '' ), 350, '' );
		$response = wp_remote_post( 'https://api.openai.com/v1/embeddings', array( 'timeout' => 45, 'headers' => array( 'Authorization' => 'Bearer ' . $openai['key'], 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'model' => sanitize_text_field( Digitalisimo_AI::get( 'knowledge_embeddings_model' ) ?: 'text-embedding-3-small' ), 'input' => $input ) ) ) );
		if ( is_wp_error( $response ) ) return array( 'ok' => false, 'message' => $response->get_error_message() ); $data = json_decode( wp_remote_retrieve_body( $response ), true ); if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 || empty( $data['data'] ) ) return array( 'ok' => false, 'message' => 'OpenAI devolvió HTTP ' . wp_remote_retrieve_response_code( $response ) );
		foreach ( $data['data'] as $position => $item ) if ( isset( $ids[ $position ], $item['embedding'] ) ) $index[ $ids[ $position ] ]['embedding'] = array_map( 'floatval', $item['embedding'] ); self::save( $index ); return array( 'ok' => true, 'message' => count( $ids ) . ' embeddings generados.' );
	}
	private static function score( $query_tokens, $record ) {
		$matches = array_intersect( $query_tokens, (array) ( $record['tokens'] ?? array() ) );
		$title = self::tokens( $record['title'] ?? '' );
		return count( $matches ) + ( 2 * count( array_intersect( $query_tokens, $title ) ) );
	}
	/** La consulta semántica sólo se solicita si el administrador la habilita explícitamente. */
	private static function query_embedding( $query ) {
		if ( ! Digitalisimo_AI::get( 'knowledge_semantic_rerank' ) || ! Digitalisimo_AI::get( 'knowledge_embeddings_enabled' ) ) return array();
		$providers = (array) Digitalisimo_AI::get( 'providers' ); $openai = (array) ( $providers['openai'] ?? array() );
		if ( empty( $openai['enabled'] ) || empty( $openai['key'] ) ) return array();
		$cache_key = 'digitalisimo_ai_query_embedding_' . md5( $query ); $cached = get_transient( $cache_key ); if ( false !== $cached ) return (array) $cached;
		$response = wp_remote_post( 'https://api.openai.com/v1/embeddings', array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $openai['key'], 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'model' => sanitize_text_field( Digitalisimo_AI::get( 'knowledge_embeddings_model' ) ?: 'text-embedding-3-small' ), 'input' => sanitize_text_field( $query ) ) ) ) );
		$data = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true ); $embedding = (array) ( $data['data'][0]['embedding'] ?? array() );
		if ( $embedding ) { $embedding = array_map( 'floatval', $embedding ); set_transient( $cache_key, $embedding, HOUR_IN_SECONDS ); }
		return $embedding;
	}
	private static function cosine( $left, $right ) { $dot = 0.0; $left_length = 0.0; $right_length = 0.0; foreach ( (array) $left as $index => $value ) { if ( ! isset( $right[ $index ] ) ) continue; $value = (float) $value; $other = (float) $right[ $index ]; $dot += $value * $other; $left_length += $value * $value; $right_length += $other * $other; } return $left_length && $right_length ? $dot / ( sqrt( $left_length ) * sqrt( $right_length ) ) : 0.0; }
	public static function search( $query, $limit = 4 ) {
		$query_tokens = self::tokens( $query ); $query_embedding = self::query_embedding( $query );
		$matches = array();
		foreach ( self::index() as $record ) { $lexical = self::score( $query_tokens, $record ); $semantic = $query_embedding && ! empty( $record['embedding'] ) ? self::cosine( $query_embedding, $record['embedding'] ) : 0.0; $score = $lexical + ( 8 * $semantic ); if ( $score > 0 ) { $record['score'] = $score; $record['semantic_score'] = $semantic; $matches[] = $record; } }
		usort( $matches, function( $a, $b ) { return $b['score'] <=> $a['score']; } );
		return array_slice( $matches, 0, max( 1, min( 8, absint( $limit ) ) ) );
	}
	public static function context( $query, $limit = 4 ) {
		if ( ! self::enabled() ) return '';
		$matches = self::search( $query, $limit );
		if ( ! $matches ) return '';
		$context = "\n\nBase de conocimiento interna del sitio (úsala sólo si responde la pregunta):";
		foreach ( $matches as $record ) $context .= "\n\nFuente: " . $record['title'] . "\nURL: " . $record['url'] . "\n" . $record['content'];
		return apply_filters( 'digitalisimo_ai_knowledge_context', $context, $matches, $query );
	}
	public static function append_context( $context, $profile, $request ) {
		$query = is_object( $request ) && isset( $request['message'] ) ? (string) $request['message'] : '';
		return $context . self::context( $query, (int) Digitalisimo_AI::get( 'knowledge_results' ) ?: 4 );
	}
	public static function count() { return count( self::index() ); }
	/** Fuentes administrativas sin contenido completo ni datos privados. */
	public static function sources() {
		return array_map( function( $record ) { return array( 'id' => $record['id'] ?? 0, 'title' => $record['title'] ?? '', 'url' => $record['url'] ?? '', 'type' => $record['type'] ?? '', 'modified' => $record['modified'] ?? '', 'embedded' => ! empty( $record['embedding'] ) ); }, self::index() );
	}
}
