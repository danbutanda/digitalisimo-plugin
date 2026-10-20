<?php
defined( 'ABSPATH' ) || exit;

/** Crea borradores editoriales desde un cliente MCP autenticado por WordPress. */
class Digitalisimo_MCP_Articles {
	public static function init() { add_action( 'rest_api_init', array( __CLASS__, 'routes' ) ); }

	public static function routes() {
		$permission = function() { return current_user_can( 'edit_posts' ); };
		register_rest_route( 'digitalisimo-mcp/v1', '/clusters', array( 'methods' => 'GET', 'permission_callback' => $permission, 'callback' => array( __CLASS__, 'clusters' ) ) );
		register_rest_route( 'digitalisimo-mcp/v1', '/create-draft', array( 'methods' => 'POST', 'permission_callback' => $permission, 'callback' => array( __CLASS__, 'create_draft' ) ) );
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

	public static function create_draft( WP_REST_Request $request ) {
		$primary = sanitize_text_field( $request->get_param( 'primary_keyword' ) );
		$secondary = self::keywords( $request->get_param( 'secondary_keywords' ) );
		$pillar_id = absint( $request->get_param( 'pillar_id' ) );
		$post_type = sanitize_key( $request->get_param( 'post_type' ) ?: 'post' );
		$title = sanitize_text_field( $request->get_param( 'title' ) );
		$description = sanitize_text_field( $request->get_param( 'description' ) );
		$content = preg_replace( '#<h1\b[^>]*>.*?</h1>\s*#is', '', (string) $request->get_param( 'content' ), 1 );
		if ( ! $primary || ! $title || ! $description || ! trim( wp_strip_all_tags( $content ) ) ) return new WP_Error( 'digitalisimo_mcp_content', 'Indica keyword principal, título, descripción y contenido.', array( 'status' => 400 ) );
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || ! post_type_supports( $post_type, 'editor' ) || ! current_user_can( $post_type_object->cap->edit_posts ) ) return new WP_Error( 'digitalisimo_mcp_type', 'El tipo de contenido no permite crear artículos.', array( 'status' => 400 ) );
		if ( $pillar_id && ( ! get_post( $pillar_id ) || 'on' !== get_post_meta( $pillar_id, 'digitalisimo_seo_pillar', true ) ) ) return new WP_Error( 'digitalisimo_mcp_pillar', 'El contenido pilar indicado no existe o no está marcado como pilar.', array( 'status' => 400 ) );
		$post_id = wp_insert_post( array( 'post_type' => $post_type, 'post_status' => 'draft', 'post_title' => $title, 'post_content' => wp_kses_post( $content ), 'post_excerpt' => wp_strip_all_tags( $description ), 'post_author' => get_current_user_id() ), true );
		if ( is_wp_error( $post_id ) ) return $post_id;
		$keywords = array_slice( array_unique( array_merge( array( $primary ), $secondary ) ), 0, 5 );
		update_post_meta( $post_id, 'digitalisimo_seo_keywords', implode( ', ', $keywords ) );
		update_post_meta( $post_id, 'digitalisimo_seo_title', $title );
		update_post_meta( $post_id, 'digitalisimo_seo_description', $description );
		if ( $pillar_id ) update_post_meta( $post_id, '_related_pillar', $pillar_id );
		return rest_ensure_response( array( 'id' => (int) $post_id, 'status' => 'draft', 'title' => $title, 'edit_url' => get_edit_post_link( $post_id, 'raw' ), 'keywords' => $keywords, 'pillar_id' => $pillar_id ) );
	}

	private static function keywords( $value ) { if ( is_array( $value ) ) $values = $value; else $values = preg_split( '/[\r\n,]+/', (string) $value ); return array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', (array) $values ) ) ) ); }
	private static function primary_keyword( $post_id ) { $keywords = self::keywords( get_post_meta( $post_id, 'digitalisimo_seo_keywords', true ) ); return $keywords[0] ?? ''; }
	private static function cluster_context( $pillar_id ) { $children = get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => '_related_pillar', 'meta_value' => $pillar_id ) ); $context = 'Contenido pilar: ' . get_the_title( $pillar_id ) . ' | URL: ' . get_permalink( $pillar_id ) . ' | Keyword: ' . self::primary_keyword( $pillar_id ) . '.'; if ( $children ) { $context .= ' Artículos ya relacionados:'; foreach ( $children as $child ) $context .= ' ' . $child->post_title . ' (' . self::primary_keyword( $child->ID ) . ');'; } return $context; }
}
