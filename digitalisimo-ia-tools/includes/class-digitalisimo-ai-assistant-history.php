<?php
defined( 'ABSPATH' ) || exit;

/**
 * El historial del Asistente IA, por usuario y por sitio, sin tablas nuevas (docs/ASSISTANT.md).
 *
 * Cada conversación es una entrada privada del tipo interno TYPE (autor = quien chatea; en una red, en la tabla de su
 * sitio) y sus mensajes viven en una sola meta JSON. No va en la meta del usuario porque WordPress carga toda esa meta
 * en cada pantalla del escritorio. Límites: MAX_CONVERSATIONS por usuario (se borran las más antiguas), MAX_MESSAGES
 * por conversación y MAX_TEXT por texto; al modelo sólo viajan los últimos MODEL_MESSAGES o MODEL_CHARS. Se borra una o
 * todas desde el Asistente, y al borrar al usuario (`delete_with_user`).
 */
final class Digitalisimo_AI_Assistant_History {
	const TYPE              = 'digitalisimo_ai_chat';
	const META              = '_digitalisimo_ai_chat';
	const MAX_CONVERSATIONS = 50;
	const MAX_MESSAGES      = 160;
	const MAX_TEXT          = 8000;
	const MODEL_MESSAGES    = 40;
	const MODEL_CHARS       = 60000;

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'label'               => 'Conversaciones del Asistente IA',
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'can_export'          => false,
				'delete_with_user'    => true,
				'supports'            => array( 'title', 'author' ),
			)
		);
	}

	/** Conversación nueva de este usuario, titulada con su primer mensaje. */
	public static function create( $user_id, $text ) {
		$title = self::title( $text );
		$id    = wp_insert_post( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'post_author' => (int) $user_id, 'post_title' => $title, 'post_content' => '', 'comment_status' => 'closed', 'ping_status' => 'closed' ), true );
		if ( is_wp_error( $id ) || ! $id ) {
			return null;
		}
		self::prune( $user_id );
		return array( 'id' => (int) $id, 'user' => (int) $user_id, 'title' => $title, 'messages' => array(), 'pending' => array(), 'provider' => 'auto', 'categories' => null, 'updated' => time() );
	}

	/** La conversación si es de este usuario; null si no existe o es de otro. */
	public static function get( $id, $user_id ) {
		$post = get_post( (int) $id );
		if ( ! $post || self::TYPE !== $post->post_type || (int) $post->post_author !== (int) $user_id || ! $user_id ) {
			return null;
		}
		$data = json_decode( (string) get_post_meta( $post->ID, self::META, true ), true );
		$data = is_array( $data ) ? $data : array();
		return array(
			'id'         => (int) $post->ID,
			'user'       => (int) $post->post_author,
			'title'      => (string) $post->post_title,
			'messages'   => is_array( $data['messages'] ?? null ) ? array_values( $data['messages'] ) : array(),
			'pending'    => is_array( $data['pending'] ?? null ) ? array_values( $data['pending'] ) : array(),
			'provider'   => sanitize_key( (string) ( $data['provider'] ?? 'auto' ) ) ?: 'auto',
			'categories' => is_array( $data['categories'] ?? null ) ? array_values( array_map( 'strval', $data['categories'] ) ) : null,
			'updated'    => self::timestamp( $post ),
		);
	}

	public static function save( array $conv ) {
		global $wpdb;
		$messages = array_values( $conv['messages'] );
		if ( count( $messages ) > self::MAX_MESSAGES ) {
			$messages = array_slice( $messages, - self::MAX_MESSAGES );
		}
		$json = wp_json_encode( array( 'v' => 1, 'messages' => $messages, 'pending' => array_values( (array) $conv['pending'] ), 'provider' => (string) $conv['provider'], 'categories' => $conv['categories'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		update_post_meta( (int) $conv['id'], self::META, wp_slash( (string) $json ) );
		// La fecha de modificación ordena el historial. Sin wp_update_post: no dispara save_post en los demás plugins.
		$wpdb->update( $wpdb->posts, array( 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ), array( 'ID' => (int) $conv['id'] ) );
		clean_post_cache( (int) $conv['id'] );
	}

	/** Las conversaciones de este usuario, de la más reciente a la más antigua. */
	public static function all( $user_id ) {
		$posts = get_posts( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'author' => (int) $user_id, 'orderby' => 'modified', 'order' => 'DESC', 'posts_per_page' => self::MAX_CONVERSATIONS, 'no_found_rows' => true, 'update_post_term_cache' => false, 'update_post_meta_cache' => false, 'suppress_filters' => true ) );
		$out   = array();
		foreach ( $posts as $post ) {
			if ( (int) $post->post_author === (int) $user_id ) {
				$out[] = array( 'id' => (int) $post->ID, 'title' => (string) $post->post_title, 'date' => gmdate( 'c', self::timestamp( $post ) ) );
			}
		}
		return $out;
	}

	public static function delete( $id, $user_id ) {
		if ( ! self::get( $id, $user_id ) ) {
			return false;
		}
		return (bool) wp_delete_post( (int) $id, true );
	}

	public static function delete_all( $user_id ) {
		$deleted = 0;
		foreach ( self::ids( $user_id, -1 ) as $id ) {
			$deleted += self::delete( $id, $user_id ) ? 1 : 0;
		}
		return $deleted;
	}

	/** Deja sólo las MAX_CONVERSATIONS más recientes del usuario. */
	public static function prune( $user_id ) {
		$ids = self::ids( $user_id, -1 );
		foreach ( array_slice( $ids, self::MAX_CONVERSATIONS ) as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	private static function ids( $user_id, $limit ) {
		return array_map( 'intval', (array) get_posts( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'author' => (int) $user_id, 'orderby' => 'modified', 'order' => 'DESC', 'posts_per_page' => $limit, 'fields' => 'ids', 'no_found_rows' => true, 'suppress_filters' => true ) ) );
	}

	private static function timestamp( $post ) {
		$time = strtotime( (string) $post->post_modified_gmt . ' UTC' );
		return $time ? $time : time();
	}

	public static function title( $text ) {
		$line = trim( (string) strtok( trim( (string) $text ) . "\n", "\n" ) );
		return Digitalisimo_AI_Assistant_Tools::clip( sanitize_text_field( $line ), 60 ) ?: 'Conversación';
	}

	/** Un mensaje guardado: `id`, `role`, `time` y sus campos, con los textos recortados. */
	public static function message( $role, array $fields = array() ) {
		foreach ( array( 'text', 'content' ) as $key ) {
			if ( isset( $fields[ $key ] ) ) {
				$fields[ $key ] = Digitalisimo_AI_Assistant_Tools::clip( (string) $fields[ $key ], 'content' === $key ? Digitalisimo_AI_Assistant_Tools::DATA_MAX + 200 : self::MAX_TEXT );
			}
		}
		return array_merge( array( 'id' => 'm' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 12 ), 'role' => (string) $role, 'time' => time() ), $fields );
	}

	/** Lo que ve el navegador de un mensaje (nunca el contenido crudo que se manda al modelo). */
	public static function display( array $m ) {
		$role = (string) ( $m['role'] ?? '' );
		$out  = array( 'id' => (string) ( $m['id'] ?? '' ), 'time' => (int) ( $m['time'] ?? 0 ) );
		if ( 'user' === $role ) {
			return $out + array( 'role' => 'me', 'text' => (string) ( $m['text'] ?? '' ) );
		}
		if ( 'assistant' === $role ) {
			return '' === (string) ( $m['text'] ?? '' ) ? null : $out + array( 'role' => 'assistant', 'text' => (string) $m['text'], 'provider' => (string) ( $m['provider'] ?? '' ) );
		}
		if ( 'tool' === $role ) {
			return $out + array( 'role' => 'tool', 'ability' => (string) ( $m['ability'] ?? '' ), 'label' => (string) ( $m['label'] ?? '' ), 'kind' => (string) ( $m['kind'] ?? '' ), 'status' => (string) ( $m['status'] ?? '' ), 'summary' => (string) ( $m['summary'] ?? '' ), 'detail' => (string) ( $m['detail'] ?? '' ) );
		}
		if ( 'notice' === $role ) {
			return $out + array( 'role' => 'system', 'tone' => (string) ( $m['tone'] ?? 'info' ), 'text' => (string) ( $m['text'] ?? '' ) );
		}
		return null;
	}

	/** Todos los mensajes visibles de una conversación. */
	public static function display_all( array $conv ) {
		return array_values( array_filter( array_map( array( __CLASS__, 'display' ), $conv['messages'] ) ) );
	}

	/**
	 * Los mensajes para el modelo en formato neutro: los últimos MODEL_MESSAGES (o MODEL_CHARS) empezando siempre en un
	 * mensaje del usuario, para que ninguna respuesta de herramienta quede sin su llamada.
	 */
	public static function for_model( array $conv, array $catalog ) {
		$messages = array();
		foreach ( $conv['messages'] as $m ) {
			$role = (string) ( $m['role'] ?? '' );
			if ( 'user' === $role ) {
				$messages[] = array( 'role' => 'user', 'text' => (string) $m['text'] );
			} elseif ( 'assistant' === $role ) {
				$calls = array();
				foreach ( (array) ( $m['calls'] ?? array() ) as $call ) {
					$calls[] = array( 'id' => (string) $call['id'], 'name' => self::tool_of( $catalog, (string) $call['ability'] ), 'input' => is_array( $call['input'] ?? null ) ? $call['input'] : array(), 'sig' => (string) ( $call['sig'] ?? '' ) );
				}
				$messages[] = array( 'role' => 'assistant', 'text' => (string) ( $m['text'] ?? '' ), 'calls' => $calls );
			} elseif ( 'tool' === $role ) {
				$messages[] = array( 'role' => 'tool', 'call' => (string) $m['call'], 'name' => self::tool_of( $catalog, (string) $m['ability'] ), 'content' => (string) $m['content'] );
			}
		}
		$start = max( 0, count( $messages ) - self::MODEL_MESSAGES );
		$chars = 0;
		for ( $i = count( $messages ) - 1; $i >= $start; $i-- ) {
			$chars += strlen( (string) wp_json_encode( $messages[ $i ] ) );
			if ( $chars > self::MODEL_CHARS ) {
				$start = $i + 1;
				break;
			}
		}
		while ( $start < count( $messages ) && 'user' !== $messages[ $start ]['role'] ) {
			$start++;
		}
		if ( $start >= count( $messages ) ) {
			// Ni el último turno cabe entero: se manda desde el último mensaje del usuario.
			for ( $start = count( $messages ) - 1; $start > 0 && 'user' !== $messages[ $start ]['role']; $start-- ) {
				continue;
			}
		}
		return array_slice( $messages, max( 0, $start ) );
	}

	private static function tool_of( array $catalog, $ability ) {
		return isset( $catalog[ $ability ] ) ? $catalog[ $ability ]['tool'] : Digitalisimo_AI_Assistant_Tools::tool_name( $ability );
	}
}
