<?php
defined( 'ABSPATH' ) || exit;

/** Base de conocimiento local para recuperación contextual (RAG) de DIGITALÍSIMO IA Tools. */
class Digitalisimo_AI_Knowledge {
	const OPTION = 'digitalisimo_ai_knowledge';
	/** Huella del registro indexado de cada entrada (antes, su fecha de modificación): si no cambia, no se reescribe el índice. */
	const META = '_digitalisimo_ai_knowledge';
	/** IDs del índice, pequeña y sin autoload: saber si una entrada está indexada sin leer el índice completo. */
	const IDS = 'digitalisimo_ai_knowledge_ids';

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'index_post' ), 90, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'remove_post' ) );
		add_action( 'digitalisimo_ai_knowledge_rebuild', array( __CLASS__, 'rebuild' ) );
		add_filter( 'digitalisimo_ai_context', array( __CLASS__, 'append_context' ), 20, 3 );
		add_action( 'admin_post_digitalisimo_ai_knowledge_rebuild', array( __CLASS__, 'request_rebuild' ) );
		add_action( 'admin_post_digitalisimo_ai_knowledge_embeddings', array( __CLASS__, 'request_embeddings' ) );
	}

	private static function enabled() { return (bool) Digitalisimo_AI::get( 'knowledge_enabled' ); }
	/** Leer el índice entero repara además la lista de IDs si quedó desfasada (otra versión del plugin, dos guardados a la vez). */
	private static function index() { $index = (array) get_option( self::OPTION, array() ); self::sync_ids( $index ); return $index; }
	private static function save( $index ) { update_option( self::OPTION, $index, false ); update_option( self::IDS, array_map( 'intval', array_keys( $index ) ), false ); }
	private static function sync_ids( array $index ) {
		$keys = array_map( 'intval', array_keys( $index ) );
		$ids  = get_option( self::IDS, null );
		if ( is_array( $ids ) ) { $ids = array_map( 'intval', $ids ); if ( ! array_diff( $keys, $ids ) && ! array_diff( $ids, $keys ) ) return; }
		elseif ( ! $keys ) return;
		update_option( self::IDS, $keys, false );
	}
	/** IDs indexados. Un índice de una versión anterior (sin la lista) la crea al leerse; uno vacío no escribe nada. */
	private static function ids() {
		$ids = get_option( self::IDS, null );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array_map( 'intval', array_keys( self::index() ) );
	}
	private static function in_list( $post_id ) { return in_array( (int) $post_id, self::ids(), true ); }
	/**
	 * Para sacar algo del índice no basta la lista: una entrada con huella (META, la escriben esta versión y las
	 * anteriores al indexar) también cuenta, por si la lista quedó desfasada. Lo que no es público sale siempre.
	 */
	private static function maybe_indexed( $post_id ) { return self::in_list( $post_id ) || '' !== (string) get_post_meta( (int) $post_id, self::META, true ); }
	/** Huella de lo que se indexa (sin la fecha de modificación, que cambia en cada guardado aunque el texto sea el mismo). */
	private static function fingerprint( $record ) { unset( $record['modified'] ); return md5( serialize( $record ) ); }
	/** Sólo contenido público: de un tipo público (como en rebuild()), publicado, sin contraseña y sin noindex. Lo protegido nunca llega al chatbot. */
	private static function allowed( $post ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) return false;
		$type = get_post_type_object( $post->post_type ); if ( ! $type || empty( $type->public ) ) return false;
		$robots = (array) get_post_meta( $post->ID, 'digitalisimo_seo_robots', true );
		return post_type_supports( $post->post_type, 'editor' ) && ! in_array( 'noindex', $robots, true );
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

	/**
	 * Guardar una entrada sólo lee y reescribe el índice completo (una opción que crece con todo el contenido público)
	 * cuando cambia: una entrada que no se puede indexar y no estaba (menús, pedidos, borradores, autoguardados) no lo
	 * toca, y una indexada cuyo registro es idéntico (el segundo guardado del editor de bloques, el de los metaboxes)
	 * tampoco, así conserva además su embedding. Lo que deja de ser público sale siempre.
	 */
	public static function index_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || ! self::enabled() || ! Digitalisimo_AI::get( 'knowledge_auto_index' ) ) return;
		if ( ! self::allowed( $post ) ) {
			if ( ! self::maybe_indexed( $post_id ) ) return;
			$index = self::index(); if ( isset( $index[ $post_id ] ) ) { unset( $index[ $post_id ] ); self::save( $index ); }
			delete_post_meta( $post_id, self::META );
			return;
		}
		$record = self::record( $post );
		if ( ! $record ) return;
		$fingerprint = self::fingerprint( $record );
		if ( $fingerprint === get_post_meta( $post_id, self::META, true ) && self::in_list( $post_id ) ) return;
		$index = self::index(); $index[ $post_id ] = $record; self::save( $index ); update_post_meta( $post_id, self::META, $fingerprint );
	}

	/** Al borrar una entrada (y cada una de sus revisiones), el índice sólo se lee si la entrada estaba en él. */
	public static function remove_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! self::maybe_indexed( $post_id ) ) return;
		$index = self::index(); if ( isset( $index[ $post_id ] ) ) { unset( $index[ $post_id ] ); self::save( $index ); }
	}
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
		$index = self::index(); $ids = array_keys( self::public_index( $index ) ); if ( ! $ids ) return array( 'ok' => false, 'message' => 'No hay fuentes indexadas.' ); $ids = array_slice( $ids, 0, 20 ); $input = array(); foreach ( $ids as $id ) $input[] = wp_trim_words( ( $index[ $id ]['title'] ?? '' ) . "\n" . ( $index[ $id ]['content'] ?? '' ), 350, '' );
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
		// Un índice anterior puede conservar contenido que después pasó a privado o recibió contraseña: se comprueba al usarlo.
		$limit = max( 1, min( 8, absint( $limit ) ) ); $results = array();
		foreach ( $matches as $record ) { if ( count( $results ) >= $limit ) break; if ( self::allowed( get_post( absint( $record['id'] ?? 0 ) ) ) ) $results[] = $record; }
		return $results;
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
	/** Sólo lo que hoy es público: un índice desfasado puede conservar algo que pasó a privado o se borró. */
	private static function public_index( $index = null ) {
		$index = is_array( $index ) ? $index : self::index();
		if ( $index && function_exists( '_prime_post_caches' ) ) _prime_post_caches( array_map( 'intval', array_keys( $index ) ), false, true );
		return array_filter( $index, function( $record, $id ) { return self::allowed( get_post( (int) $id ) ); }, ARRAY_FILTER_USE_BOTH );
	}
	public static function sources() {
		return array_map( function( $record ) { return array( 'id' => $record['id'] ?? 0, 'title' => $record['title'] ?? '', 'url' => $record['url'] ?? '', 'type' => $record['type'] ?? '', 'modified' => $record['modified'] ?? '', 'embedded' => ! empty( $record['embedding'] ) ); }, self::public_index() );
	}
}
