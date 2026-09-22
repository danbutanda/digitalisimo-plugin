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
		register_rest_route( self::API_NAMESPACE, '/generate', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_create' ), 'callback' => array( __CLASS__, 'generate' ) ) );
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
			'article_chat_supported' => true,
			'allowed_types' => array_values( array_filter( array( 'post', 'page' ), function( $type ) {
				$object = get_post_type_object( $type );
				return $object && post_type_supports( $type, 'editor' ) && current_user_can( $object->cap->edit_posts );
			} ) ),
			'media_upload_url' => rest_url( 'wp/v2/media' ),
			'article_url'      => rest_url( self::API_NAMESPACE . '/articles' ),
			'generate_url'     => rest_url( self::API_NAMESPACE . '/generate' ),
			'editorial_playbook' => Digitalisimo_Integrations_Content_Playbook::profile(), 'editorial_profile_version' => Digitalisimo_Integrations_Content_Playbook::version(),
		) );
	}

	/** Genera una propuesta estructurada con el perfil content de IA Tools; nunca publica. */
	public static function generate( WP_REST_Request $request ) {
		$primary = sanitize_text_field( (string) $request->get_param( 'primary_keyword' ) );
		if ( ! $primary ) return new WP_Error( 'digitalisimo_primary_keyword', 'Indica una keyword principal.', array( 'status' => 400 ) );
		$secondary = (array) $request->get_param( 'secondary_keywords' );
		$result = Digitalisimo_Integrations_Content_Playbook::generate( $primary, $secondary, sanitize_text_field( (string) $request->get_param( 'title' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}


	/**
	 * Validate and normalize an article chat before creating the draft.
	 * The stored value is a JSON STRING, matching the editorial metabox.
	 */
	private static function sanitize_article_chat( $raw ) {
		$data = is_array( $raw ) ? $raw : ( is_string( $raw ) ? json_decode( $raw, true ) : null );
		if ( ! is_array( $data ) || ( is_string( $raw ) && json_last_error() !== JSON_ERROR_NONE ) ||
			! isset( $data['speakers'], $data['messages'] ) ||
			! is_array( $data['speakers'] ) || ! is_array( $data['messages'] ) ||
			! $data['speakers'] || ! $data['messages'] ) {
			return new WP_Error( 'digitalisimo_invalid_article_chat', 'El chat debe ser un JSON válido con speakers y messages no vacíos.', array( 'status' => 400 ) );
		}
		$speakers = array();
		$ids = array();
		foreach ( $data['speakers'] as $row ) {
			if ( ! is_array( $row ) ) return new WP_Error( 'digitalisimo_invalid_article_chat', 'Personaje inválido.', array( 'status' => 400 ) );
			$id = sanitize_key( (string) ( $row['id'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			$align = (string) ( $row['align'] ?? '' );
			$image_id = $row['image_id'] ?? 0;
			if ( ! $id || ! $name || isset( $ids[ $id ] ) ||
				! in_array( $align, array( 'start', 'center', 'end' ), true ) ||
				! is_numeric( $image_id ) || (int) $image_id < 0 ) {
				return new WP_Error( 'digitalisimo_invalid_article_chat', 'El chat incluye personajes duplicados o campos inválidos.', array( 'status' => 400 ) );
			}
			$ids[ $id ] = true;
			$speakers[] = array(
				'id' => $id, 'name' => $name,
				'icon' => sanitize_text_field( (string) ( $row['icon'] ?? '💬' ) ),
				'image_id' => absint( $image_id ), 'align' => $align,
			);
		}
		$messages = array();
		foreach ( $data['messages'] as $row ) {
			if ( ! is_array( $row ) ) return new WP_Error( 'digitalisimo_invalid_article_chat', 'Mensaje inválido.', array( 'status' => 400 ) );
			$speaker = sanitize_key( (string) ( $row['speaker'] ?? '' ) );
			$text = sanitize_textarea_field( (string) ( $row['text'] ?? '' ) );
			if ( ! isset( $ids[ $speaker ] ) || '' === $text ) {
				return new WP_Error( 'digitalisimo_invalid_article_chat', 'Un mensaje está vacío o usa un personaje inexistente.', array( 'status' => 400 ) );
			}
			$messages[] = array( 'speaker' => $speaker, 'text' => $text );
		}
		return wp_json_encode( array( 'speakers' => $speakers, 'messages' => $messages ), JSON_UNESCAPED_UNICODE );
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

		$article_chat = null;
		if ( $request->has_param( 'digitalisimo_article_chat' ) ) {
			// El chat puede insertarse en el cuerpo o en una plantilla individual de Elementor.
			// Guardar los datos no debe depender de dónde se coloque el shortcode.
			$article_chat = self::sanitize_article_chat( $request->get_param( 'digitalisimo_article_chat' ) );
			if ( is_wp_error( $article_chat ) ) return $article_chat;
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

		if ( null !== $article_chat ) update_post_meta( $post_id, 'digitalisimo_article_chat', $article_chat );
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
			'article_chat_saved' => null !== $article_chat,
			'site_url'       => home_url( '/' ),
		) );
	}
}
