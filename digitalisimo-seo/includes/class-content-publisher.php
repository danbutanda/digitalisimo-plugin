<?php
defined( 'ABSPATH' ) || exit;

/**
 * API editorial nativa: recibe contenido ya generado, sin llamadas a modelos.
 * Cada sitio WordPress (individual o de una red) expone su propio endpoint.
 */
class Digitalisimo_Integrations_Content_Publisher {
	const API_NAMESPACE = 'digitalisimo-publisher/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route( self::API_NAMESPACE, '/site', array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => array( __CLASS__, 'can_create' ),
			'callback'            => array( __CLASS__, 'site' ),
		) );
		register_rest_route( self::API_NAMESPACE, '/articles', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => array( __CLASS__, 'can_create' ),
			'callback'            => array( __CLASS__, 'create_article' ),
		) );
	}

	public static function can_create() {
		return current_user_can( 'edit_posts' );
	}

	public static function site() {
		return rest_ensure_response( array(
			'name'          => get_bloginfo( 'name' ),
			'url'           => home_url( '/' ),
			'blog_id'       => get_current_blog_id(),
			'is_multisite'  => is_multisite(),
			'seo_enabled'   => (bool) Digitalisimo_Integrations_Settings::get( 'enable_seo' ),
			'allowed_types' => array_values( array_filter( array( 'post', 'page' ), function( $type ) {
				$object = get_post_type_object( $type );
				return $object && post_type_supports( $type, 'editor' ) && current_user_can( $object->cap->edit_posts );
			} ) ),
			'media_upload_url' => rest_url( 'wp/v2/media' ),
			'article_url'      => rest_url( self::API_NAMESPACE . '/articles' ),
		) );
	}

	/** Fase 1: solo borradores; nunca publica por accidente. */
	public static function create_article( WP_REST_Request $request ) {
		if ( ! Digitalisimo_Integrations_Settings::get( 'enable_seo' ) ) {
			return new WP_Error( 'digitalisimo_seo_inactive', 'Activa Digitalisimo SEO antes de crear contenido.', array( 'status' => 409 ) );
		}

		$status = $request->get_param( 'status' );
		if ( null !== $status && 'draft' !== $status ) {
			return new WP_Error( 'digitalisimo_draft_only', 'Esta primera versión solo crea borradores.', array( 'status' => 400 ) );
		}

		$type = sanitize_key( $request->get_param( 'post_type' ) ?: 'post' );
		if ( ! in_array( $type, array( 'post', 'page' ), true ) ) {
			return new WP_Error( 'digitalisimo_invalid_type', 'Utiliza post o page.', array( 'status' => 400 ) );
		}
		$object = get_post_type_object( $type );
		if ( ! $object || ! post_type_supports( $type, 'editor' ) || ! current_user_can( $object->cap->edit_posts ) ) {
			return new WP_Error( 'digitalisimo_type_forbidden', 'No tienes permisos para crear este contenido.', array( 'status' => 403 ) );
		}

		$title       = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$seo_title   = sanitize_text_field( (string) $request->get_param( 'seo_title' ) );
		$description = sanitize_textarea_field( (string) $request->get_param( 'meta_description' ) );
		$primary     = sanitize_text_field( (string) $request->get_param( 'primary_keyword' ) );
		$content     = (string) $request->get_param( 'content' );
		$content     = preg_replace( '#^\s*<h1\b[^>]*>.*?</h1>\s*#is', '', $content, 1 );
		$content     = wp_kses_post( $content );
		if ( '' === $title || '' === $primary || '' === $description || '' === trim( wp_strip_all_tags( $content ) ) ) {
			return new WP_Error( 'digitalisimo_missing_content', 'Indica título, contenido, palabra clave principal y metadescripción.', array( 'status' => 400 ) );
		}

		$attachment_id = absint( $request->get_param( 'featured_media' ) );
		if ( $attachment_id && ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) ) {
			return new WP_Error( 'digitalisimo_invalid_media', 'La imagen debe existir en este sitio y ser editable por tu usuario.', array( 'status' => 403 ) );
		}

		$category_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $request->get_param( 'category_ids' ) ) ) ) );
		if ( $category_ids ) {
			if ( 'post' !== $type || ! current_user_can( get_taxonomy( 'category' )->cap->assign_terms ) ) {
				return new WP_Error( 'digitalisimo_categories_forbidden', 'No puedes asignar categorías a este contenido.', array( 'status' => 403 ) );
			}
			foreach ( $category_ids as $term_id ) {
				if ( ! term_exists( $term_id, 'category' ) ) {
					return new WP_Error( 'digitalisimo_invalid_category', 'Una categoría no existe en este sitio.', array( 'status' => 400 ) );
				}
			}
		}

		$pillar_id = absint( $request->get_param( 'pillar_id' ) );
		if ( $pillar_id && ( ! get_post( $pillar_id ) || 'on' !== get_post_meta( $pillar_id, 'digitalisimo_seo_pillar', true ) ) ) {
			return new WP_Error( 'digitalisimo_invalid_pillar', 'El contenido pilar no existe en este sitio.', array( 'status' => 400 ) );
		}

		$post_id = wp_insert_post( array(
			'post_type'    => $type,
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_name'    => sanitize_title( $request->get_param( 'slug' ) ?: $title ),
			'post_content' => $content,
			'post_excerpt' => sanitize_textarea_field( (string) ( $request->get_param( 'excerpt' ) ?: $description ) ),
			'post_author'  => get_current_user_id(),
		), true );
		if ( is_wp_error( $post_id ) ) return $post_id;

		$secondary = $request->get_param( 'secondary_keywords' );
		$secondary = is_array( $secondary ) ? $secondary : preg_split( '/[,\r\n]+/', (string) $secondary );
		$keywords  = array_unique( array_filter( array_map( 'sanitize_text_field', array_merge( array( $primary ), array_map( 'trim', $secondary ) ) ) ) );
		$limit     = max( 1, absint( Digitalisimo_Integrations_Settings::get( 'keyword_max_count', 5 ) ) );
		$keywords  = array_slice( array_values( $keywords ), 0, $limit );

		update_post_meta( $post_id, 'digitalisimo_seo_title', $seo_title ?: $title );
		update_post_meta( $post_id, 'digitalisimo_seo_description', $description );
		update_post_meta( $post_id, 'digitalisimo_seo_keywords', implode( ', ', $keywords ) );
		if ( $request->has_param( 'canonical' ) ) update_post_meta( $post_id, 'digitalisimo_seo_canonical', esc_url_raw( (string) $request->get_param( 'canonical' ) ) );
		if ( $request->has_param( 'social_title' ) ) update_post_meta( $post_id, 'digitalisimo_seo_social_title', sanitize_text_field( (string) $request->get_param( 'social_title' ) ) );
		if ( $request->has_param( 'social_description' ) ) update_post_meta( $post_id, 'digitalisimo_seo_social_description', sanitize_textarea_field( (string) $request->get_param( 'social_description' ) ) );
		if ( $pillar_id ) update_post_meta( $post_id, '_related_pillar', $pillar_id );
		if ( $category_ids ) wp_set_post_terms( $post_id, $category_ids, 'category' );
		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
			if ( $request->has_param( 'image_alt' ) ) update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $request->get_param( 'image_alt' ) ) );
		}

		return rest_ensure_response( array(
			'id'             => (int) $post_id,
			'status'         => 'draft',
			'edit_url'       => get_edit_post_link( $post_id, 'raw' ),
			'preview_url'    => get_preview_post_link( $post_id ),
			'featured_media' => $attachment_id,
			'keywords'       => $keywords,
			'site_url'       => home_url( '/' ),
		) );
	}
}
