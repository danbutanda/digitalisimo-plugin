<?php
/** Duplicación editorial por sitio; no carga recursos en el frontend. */
defined( 'ABSPATH' ) || exit;

final class Digitalisimo_Elements_Duplicator {
	const ACTION = 'digitalisimo_elements_duplicate_post';

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'row_action' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	private static function post_types() {
		$types = get_post_types( array( 'public' => true, 'show_ui' => true ), 'names' );
		$types = is_array( $types ) ? array_values( $types ) : array();
		$templates = get_post_type_object( 'elementor_library' );
		if ( $templates && $templates->show_ui ) {
			$types[] = 'elementor_library';
		}
		// Contenidos transaccionales y archivos de medios requieren sus propios flujos de copia.
		$types = array_diff( $types, array( 'attachment', 'product', 'product_variation', 'shop_order', 'shop_coupon', 'shop_subscription' ) );
		$types = apply_filters( 'digitalisimo_elements_duplicator_post_types', $types );
		if ( ! is_array( $types ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( $types, static function ( $type ) {
			$object = is_string( $type ) ? get_post_type_object( $type ) : false;
			return $object && $object->show_ui && ! in_array( $type, array( 'attachment', 'revision', 'product_variation', 'shop_order' ), true );
		} ) ) );
	}

	private static function allowed( $post ) {
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::post_types(), true ) || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return false;
		}
		$type = get_post_type_object( $post->post_type );
		return $type && current_user_can( 'edit_post', $post->ID ) && current_user_can( $type->cap->create_posts );
	}

	public static function row_action( $actions, $post ) {
		if ( ! self::allowed( $post ) ) {
			return $actions;
		}
		$url = add_query_arg(
			array( 'action' => self::ACTION, 'post' => $post->ID ),
			admin_url( 'admin-post.php' )
		);
		$url = wp_nonce_url( $url, self::nonce_action( $post->ID ) );
		$actions['digitalisimo_elements_duplicate'] = '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( 'Duplicar %s como borrador', $post->post_title ) ) . '">Duplicar</a>';
		return $actions;
	}

	private static function nonce_action( $post_id ) {
		return self::ACTION . '_' . get_current_blog_id() . '_' . $post_id;
	}

	public static function handle() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( 'Solicitud no válida.', '', array( 'response' => 405 ) );
		}
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $post_id || ! wp_verify_nonce( $nonce, self::nonce_action( $post_id ) ) ) {
			wp_die( 'Enlace de duplicación no válido o vencido.', '', array( 'response' => 403 ) );
		}
		$post = get_post( $post_id );
		if ( ! self::allowed( $post ) ) {
			wp_die( 'No tienes permiso para duplicar este contenido.', '', array( 'response' => 403 ) );
		}
		$new_id = self::duplicate( $post );
		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ), '', array( 'response' => 500 ) );
		}
		$url = get_edit_post_link( $new_id, 'raw' );
		wp_safe_redirect( $url ? $url : admin_url( 'edit.php?post_type=' . rawurlencode( $post->post_type ) ) );
		exit;
	}

	/** Crea un borrador en el blog actual y conserva los datos editoriales de Elementor. */
	public static function duplicate( $post ) {
		if ( ! self::allowed( $post ) ) {
			return new WP_Error( 'digitalisimo_duplicate_forbidden', 'No tienes permiso para duplicar este contenido.' );
		}
		$new_id = wp_insert_post(
			array(
				'post_type'             => $post->post_type,
				'post_status'           => 'draft',
				'post_title'            => $post->post_title . ' (copia)',
				'post_content'          => $post->post_content,
				'post_content_filtered' => $post->post_content_filtered,
				'post_excerpt'          => $post->post_excerpt,
				'post_author'           => get_current_user_id(),
				'post_parent'           => $post->post_parent,
				'post_password'         => $post->post_password,
				'comment_status'        => $post->comment_status,
				'ping_status'           => $post->ping_status,
				'menu_order'            => $post->menu_order,
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		if ( ! $new_id ) {
			return new WP_Error( 'digitalisimo_duplicate_insert', 'No se pudo crear el borrador.' );
		}

		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) ) {
				return self::rollback( $new_id, $terms );
			}
			$result = wp_set_object_terms( $new_id, array_map( 'intval', $terms ), $taxonomy, false );
			if ( is_wp_error( $result ) ) {
				return self::rollback( $new_id, $result );
			}
		}

		$skip = array(
			'_edit_lock', '_edit_last', '_wp_old_slug', '_elementor_css', '_elementor_page_assets', '_elementor_controls_usage',
			'digitalisimo_seo_canonical', '_yoast_wpseo_canonical', 'rank_math_canonical_url',
		);
		$skip = apply_filters( 'digitalisimo_elements_duplicator_skip_meta', $skip, $post );
		$skip = is_array( $skip ) ? $skip : array();
		foreach ( get_post_meta( $post->ID ) as $key => $values ) {
			if ( in_array( $key, $skip, true ) || 0 === strpos( $key, '_wp_trash_meta_' ) ) {
				continue;
			}
			foreach ( $values as $raw_value ) {
				// WordPress quita barras al guardar; wp_slash preserva el JSON de _elementor_data.
				$value = wp_slash( maybe_unserialize( $raw_value ) );
				if ( false === add_post_meta( $new_id, $key, $value ) ) {
					return self::rollback( $new_id, new WP_Error( 'digitalisimo_duplicate_meta', 'No se pudieron copiar los metadatos del contenido.' ) );
				}
			}
		}
		return $new_id;
	}

	private static function rollback( $new_id, $error ) {
		wp_delete_post( $new_id, true );
		return $error;
	}
}
