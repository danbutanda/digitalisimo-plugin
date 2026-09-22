<?php
defined( 'ABSPATH' ) || exit;

/** Crea borradores editoriales desde un cliente MCP autenticado por WordPress. */
class Digitalisimo_MCP_Articles {
	public static function init() { add_action( 'rest_api_init', array( __CLASS__, 'routes' ) ); }

	public static function routes() {
		$permission = function() { return current_user_can( 'edit_posts' ); };
		register_rest_route( 'digitalisimo-mcp/v1', '/clusters', array( 'methods' => 'GET', 'permission_callback' => $permission, 'callback' => array( __CLASS__, 'clusters' ) ) );
		register_rest_route( 'digitalisimo-mcp/v1', '/generate-article', array( 'methods' => 'POST', 'permission_callback' => $permission, 'callback' => array( __CLASS__, 'generate_article' ) ) );
	}

	public static function clusters() {
		$pillars = get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'meta_key' => 'digitalisimo_seo_pillar', 'meta_value' => 'on', 'orderby' => 'title', 'order' => 'ASC' ) );
		$rows = array();
		foreach ( $pillars as $pillar ) {
			$children = get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'posts_per_page' => -1, 'meta_key' => '_related_pillar', 'meta_value' => $pillar->ID, 'fields' => 'ids' ) );
			$rows[] = array( 'id' => (int) $pillar->ID, 'title' => $pillar->post_title, 'url' => get_permalink( $pillar ), 'keyword' => self::primary_keyword( $pillar->ID ), 'children' => array_map( function( $id ) { return array( 'id' => (int) $id, 'title' => get_the_title( $id ), 'keyword' => self::primary_keyword( $id ) ); }, $children ) );
		}
		return rest_ensure_response( array( 'pillars' => $rows ) );
	}

	public static function generate_article( WP_REST_Request $request ) {
		$primary = sanitize_text_field( $request->get_param( 'primary_keyword' ) );
		$secondary = self::keywords( $request->get_param( 'secondary_keywords' ) );
		$pillar_id = absint( $request->get_param( 'pillar_id' ) );
		$post_type = sanitize_key( $request->get_param( 'post_type' ) ?: 'post' );
		if ( ! $primary ) return new WP_Error( 'digitalisimo_mcp_keyword', 'Indica una palabra clave principal.', array( 'status' => 400 ) );
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || ! post_type_supports( $post_type, 'editor' ) || ! current_user_can( $post_type_object->cap->edit_posts ) ) return new WP_Error( 'digitalisimo_mcp_type', 'El tipo de contenido no permite crear artículos.', array( 'status' => 400 ) );
		if ( $pillar_id && ( ! get_post( $pillar_id ) || 'on' !== get_post_meta( $pillar_id, 'digitalisimo_seo_pillar', true ) ) ) return new WP_Error( 'digitalisimo_mcp_pillar', 'El contenido pilar indicado no existe o no está marcado como pilar.', array( 'status' => 400 ) );

		$cluster_context = $pillar_id ? self::cluster_context( $pillar_id ) : 'No se asignó contenido pilar. Crea un artículo independiente, sin inventar enlaces internos.';
		$prompt = 'Redacta un artículo SEO en español latinoamericano para WordPress. Keyword principal: ' . $primary . '. Keywords secundarias: ' . implode( ', ', $secondary ) . ".\n" . $cluster_context . "\nDevuelve exclusivamente JSON válido con title, description y content. title tiene máximo 60 caracteres; description 120 a 160 caracteres; content es HTML semántico con un H1 único, introducción, H2/H3 útiles, listas cuando ayuden, conclusión y al menos 900 palabras. No inventes testimonios, cifras, fuentes, enlaces ni servicios. Si existe contenido pilar, incluye una sola referencia editorial natural usando su URL exacta. No añadas meta etiquetas ni markdown.";
		$answer = Digitalisimo_AI::complete( 'content', $prompt, 'Contenido público y relación editorial del sitio: ' . $cluster_context );
		if ( is_wp_error( $answer ) ) return $answer;
		$data = self::article_data( $answer );
		if ( is_wp_error( $data ) ) return $data;
		$post_id = wp_insert_post( array( 'post_type' => $post_type, 'post_status' => 'draft', 'post_title' => $data['title'], 'post_content' => wp_kses_post( $data['content'] ), 'post_excerpt' => wp_strip_all_tags( $data['description'] ), 'post_author' => get_current_user_id() ), true );
		if ( is_wp_error( $post_id ) ) return $post_id;
		$keywords = array_slice( array_unique( array_merge( array( $primary ), $secondary ) ), 0, 5 );
		update_post_meta( $post_id, 'digitalisimo_seo_keywords', implode( ', ', $keywords ) );
		update_post_meta( $post_id, 'digitalisimo_seo_title', $data['title'] );
		update_post_meta( $post_id, 'digitalisimo_seo_description', $data['description'] );
		if ( $pillar_id ) update_post_meta( $post_id, '_related_pillar', $pillar_id );
		return rest_ensure_response( array( 'id' => (int) $post_id, 'status' => 'draft', 'title' => $data['title'], 'edit_url' => get_edit_post_link( $post_id, 'raw' ), 'keywords' => $keywords, 'pillar_id' => $pillar_id ) );
	}

	private static function keywords( $value ) { if ( is_array( $value ) ) $values = $value; else $values = preg_split( '/[\r\n,]+/', (string) $value ); return array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', (array) $values ) ) ) ); }
	private static function primary_keyword( $post_id ) { $keywords = self::keywords( get_post_meta( $post_id, 'digitalisimo_seo_keywords', true ) ); return $keywords[0] ?? ''; }
	private static function cluster_context( $pillar_id ) { $children = get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => '_related_pillar', 'meta_value' => $pillar_id ) ); $context = 'Contenido pilar: ' . get_the_title( $pillar_id ) . ' | URL: ' . get_permalink( $pillar_id ) . ' | Keyword: ' . self::primary_keyword( $pillar_id ) . '.'; if ( $children ) { $context .= ' Artículos ya relacionados:'; foreach ( $children as $child ) $context .= ' ' . $child->post_title . ' (' . self::primary_keyword( $child->ID ) . ');'; } return $context; }
	private static function article_data( $answer ) { $answer = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', (string) $answer ) ); $data = json_decode( $answer, true ); if ( ! is_array( $data ) || empty( $data['title'] ) || empty( $data['description'] ) || empty( $data['content'] ) ) return new WP_Error( 'digitalisimo_mcp_response', 'La IA no devolvió el formato de artículo esperado. Intenta nuevamente.', array( 'status' => 502 ) ); return array( 'title' => sanitize_text_field( $data['title'] ), 'description' => sanitize_text_field( $data['description'] ), 'content' => (string) $data['content'] ); }
}
