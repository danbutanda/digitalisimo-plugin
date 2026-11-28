<?php
defined( 'ABSPATH' ) || exit;

/**
 * El historial del Asistente IA, por usuario y por sitio, sin tablas nuevas (docs/ASSISTANT.md).
 *
 * Cada conversación es una entrada privada del tipo interno TYPE (autor = quien chatea; en una red, en la tabla de su
 * sitio) y sus mensajes viven en una sola meta JSON. No va en la meta del usuario porque WordPress carga toda esa meta
 * en cada pantalla del escritorio. Límites: MAX_CONVERSATIONS por usuario (se borran las más antiguas), MAX_MESSAGES
 * por conversación y MAX_TEXT por texto. Se borra una o todas desde el Asistente, y al borrar al usuario
 * (`delete_with_user`).
 *
 * Memoria (caso plomerito del 2026-11-23: el Asistente olvidó el producto y el precio porque un turno con 19 consultas
 * empujó fuera de la ventana de 40 mensajes / 60 000 caracteres lo que la persona había dicho): al modelo viaja la
 * conversación COMPLETA, con los resultados de herramientas, mientras quepa en el contexto real del modelo
 * (context_chars(), por proveedor y modelo, filtro `digitalisimo_ai_assistant_context_chars`). Sólo cerca de ese límite
 * se compactan los resultados de turnos anteriores (los más viejos primero, «[resultado anterior recortado: N
 * caracteres]») y, si aun así no cabe, los turnos más viejos se resumen en un mensaje inicial que conserva TODO lo que
 * dijo la persona y lo que respondió el asistente. Nunca se borra un mensaje suelto: cada llamada sigue con su resultado.
 * Lo guardado se acota igual: los resultados más viejos se compactan cuando su total pasa de STORE_TOOL_BYTES y, pasado
 * MAX_MESSAGES, los turnos más viejos pasan a un mensaje `summary` (no se muestra en pantalla; el modelo sí lo recibe).
 */
final class Digitalisimo_AI_Assistant_History {
	const TYPE              = 'digitalisimo_ai_chat';
	const META              = '_digitalisimo_ai_chat';
	const MAX_CONVERSATIONS = 50;
	const MAX_MESSAGES      = 600;
	const MAX_TEXT          = 8000;
	/** Tope de lo guardado en resultados de herramientas (bytes); lo más viejo se compacta (cabe en max_allowed_packet). */
	const STORE_TOOL_BYTES  = 3000000;
	/** Tamaño de un resultado compactado (bytes). */
	const COMPACT_BYTES     = 1500;
	/**
	 * Tope de cada resultado de herramienta de un turno anterior al modelo (bytes; docs/ASSISTANT.md, «Contexto»): ya
	 * cumplió su papel y, si hace falta otra vez, se vuelve a consultar. Filtro `digitalisimo_ai_assistant_old_result_bytes`
	 * (0: enteros, como antes).
	 */
	const OLD_RESULT_BYTES  = 1500;
	/** Contexto por defecto si no se conoce el modelo (caracteres de JSON; unos 100 000 tokens). */
	const CONTEXT_DEFAULT   = 350000;
	/** Fracción del contexto real que se usa para la petición (lo demás: la respuesta y el margen de la estimación). */
	const CONTEXT_SHARE     = 0.7;
	/**
	 * Caracteres de JSON por token, a la baja (medido en los cuerpos reales del caso plomerito, 2026-11-27: unos 2,5 con
	 * Haiku, Sonnet y Opus 5.5; el JSON de WordPress escapa los acentos y eso cuenta).
	 */
	const CHARS_PER_TOKEN   = 2.4;
	/**
	 * Tope de la petición ENTERA (herramientas, instrucciones y mensajes) por precio, en tokens: Claude Haiku 5.5 cobra 5
	 * veces más por toda la petición si pasa de 100 000; con 85 000 queda margen para la estimación.
	 */
	const PRICE_TOKENS      = array( 'claude-haiku-5' => 85000 );
	/** Tope del resumen guardado (caracteres) y de cada línea suya. */
	const SUMMARY_MAX       = 200000;
	const SUMMARY_LINE      = 2000;
	/** Contexto de cada familia de modelos, en tokens (prefijo del ID => tokens). */
	const CONTEXT_TOKENS    = array(
		'claude-opus-5'     => 1000000,
		'claude-sonnet-5'   => 1000000,
		'claude-fable'      => 1000000,
		'claude-haiku-5'    => 1000000,
		'claude-opus-4-8'   => 1000000,
		'claude-opus-4-7'   => 1000000,
		'claude-opus-4-6'   => 1000000,
		'claude-sonnet-4-6' => 1000000,
		'claude-'           => 200000,
		'gpt-4.1'           => 1000000,
		'gpt-5'             => 400000,
		'gpt-4o'            => 128000,
		'o3'                => 200000,
		'o4'                => 200000,
		'gemini-2.5'        => 1000000,
		'gemini-3'          => 1000000,
		'grok-4'            => 256000,
		'grok-3'            => 131072,
		'deepseek'          => 128000,
	);

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
				// Capacidades propias que ningún rol tiene: ni XML-RPC (wp.getPosts, wp.editPost, wp.deletePost) ni ninguna
				// otra vía con permisos de entradas llega a estas conversaciones. El Asistente las lee por su autor.
				'capability_type'     => array( 'digitalisimo_ai_chat', 'digitalisimo_ai_chats' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/** Ni un superadministrador edita, lee ni borra por capacidades la conversación de otra persona. */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'read_post', 'delete_post', 'publish_post' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		// Como el núcleo: el argumento puede ser un ID o un WP_Post. Sólo cuenta el tipo del Asistente.
		$post = $args[0] instanceof WP_Post ? $args[0] : ( is_numeric( $args[0] ) ? get_post( (int) $args[0] ) : null );
		if ( $post && self::TYPE === $post->post_type && (int) $post->post_author !== (int) $user_id ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	/**
	 * Al borrar a un usuario (o quitarlo de un sitio de la red) sus conversaciones se borran con él, también cuando su
	 * contenido se atribuye a otra persona: `delete_with_user` sólo vale sin reasignar, y la reasignación cambiaría el
	 * autor de conversaciones privadas.
	 */
	public static function on_user_removed( $user_id ) {
		self::delete_all( (int) $user_id );
	}

	/** Conversación nueva de este usuario, titulada con su primer mensaje. */
	public static function create( $user_id, $text ) {
		$title = self::title( $text );
		$id    = wp_insert_post( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'post_author' => (int) $user_id, 'post_title' => $title, 'post_content' => '', 'comment_status' => 'closed', 'ping_status' => 'closed' ), true );
		if ( is_wp_error( $id ) || ! $id ) {
			return null;
		}
		self::prune( $user_id );
		return array( 'id' => (int) $id, 'user' => (int) $user_id, 'title' => $title, 'messages' => array(), 'pending' => array(), 'provider' => 'auto', 'categories' => null, 'drafts' => array(), 'resume' => null, 'more_tools' => false, 'updated' => time() );
	}

	/** La conversación si es de este usuario; null si no existe o es de otro. */
	public static function get( $id, $user_id, $fresh = false ) {
		if ( $fresh ) {
			// Bajo el candado: lo que esté en la caché de objetos del proceso podría ser anterior al último guardado.
			wp_cache_delete( (int) $id, 'post_meta' );
			wp_cache_delete( (int) $id, 'posts' );
		}
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
			'drafts'     => is_array( $data['drafts'] ?? null ) ? array_values( array_unique( array_filter( array_map( 'intval', $data['drafts'] ) ) ) ) : array(),
			'resume'     => is_array( $data['resume'] ?? null ) ? $data['resume'] : null,
			// La conversación ya pidió las funciones de diseño del constructor (Tools::MORE_TOOL).
			'more_tools' => ! empty( $data['more_tools'] ),
			'updated'    => self::timestamp( $post ),
		);
	}

	public static function save( array $conv ) {
		global $wpdb;
		$messages = self::trim( self::compact_stored( array_values( $conv['messages'] ) ) );
		$json = wp_json_encode( array( 'v' => 1, 'messages' => $messages, 'pending' => array_values( (array) $conv['pending'] ), 'provider' => (string) $conv['provider'], 'categories' => $conv['categories'], 'drafts' => array_values( array_map( 'intval', (array) ( $conv['drafts'] ?? array() ) ) ), 'resume' => $conv['resume'] ?? null, 'more_tools' => ! empty( $conv['more_tools'] ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) || '' === $json ) {
			return false; // Nunca se guarda una conversación vacía en lugar de la que había.
		}
		update_post_meta( (int) $conv['id'], self::META, wp_slash( $json ) );
		// La fecha de modificación ordena el historial. Sin wp_update_post: no dispara save_post en los demás plugins.
		$wpdb->update( $wpdb->posts, array( 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ), array( 'ID' => (int) $conv['id'] ) );
		clean_post_cache( (int) $conv['id'] );
	}

	/**
	 * Los últimos MAX_MESSAGES mensajes cortando en el límite de un turno (un mensaje de la persona), para que nunca
	 * quede un resultado de herramienta sin su llamada; si un solo turno no cabe, se conserva desde su mensaje. Lo que
	 * se quita no se pierde para el modelo: los textos de la persona y del asistente pasan a un mensaje `summary` al
	 * principio (uno solo, que acumula los anteriores).
	 */
	public static function trim( array $messages ) {
		$count = count( $messages );
		if ( $count <= self::MAX_MESSAGES ) {
			return $messages;
		}
		$cut   = $count - self::MAX_MESSAGES;
		$start = null;
		for ( $i = $cut; $i < $count; $i++ ) {
			if ( 'user' === ( $messages[ $i ]['role'] ?? '' ) ) {
				$start = $i;
				break;
			}
		}
		if ( null === $start ) {
			for ( $i = $cut - 1; $i >= 0; $i-- ) {
				if ( 'user' === ( $messages[ $i ]['role'] ?? '' ) ) {
					$start = $i;
					break;
				}
			}
		}
		$start   = null === $start ? $cut : $start;
		$summary = self::fit( self::summarize( array_slice( $messages, 0, $start ) ), self::SUMMARY_MAX );
		$kept    = array_slice( $messages, $start );
		return '' === $summary ? $kept : array_merge( array( array( 'id' => 'summary', 'role' => 'summary', 'time' => time(), 'text' => $summary ) ), $kept );
	}

	/**
	 * Lo que dijo la persona y lo que respondió el asistente en unos mensajes, en texto (para el resumen de lo más viejo).
	 * Un `summary` anterior se conserva entero al principio.
	 */
	public static function summarize( array $messages ) {
		$lines = array();
		foreach ( $messages as $m ) {
			$role = (string) ( $m['role'] ?? '' );
			$text = trim( (string) ( $m['text'] ?? '' ) );
			if ( 'summary' === $role && '' !== $text ) {
				$lines[] = $text;
			} elseif ( 'user' === $role && '' !== $text ) {
				$lines[] = 'Persona: ' . Digitalisimo_AI_Assistant_Tools::clip( $text, self::SUMMARY_LINE );
			} elseif ( 'assistant' === $role ) {
				$used = array();
				foreach ( (array) ( $m['calls'] ?? array() ) as $call ) {
					$used[] = (string) ( $call['ability'] ?? $call['name'] ?? '' );
				}
				if ( '' !== $text ) {
					$lines[] = 'Asistente: ' . Digitalisimo_AI_Assistant_Tools::clip( $text, self::SUMMARY_LINE );
				}
				if ( $used ) {
					$lines[] = 'Asistente usó: ' . implode( ', ', array_unique( array_filter( $used ) ) );
				}
			}
		}
		return $lines ? implode( "\n", $lines ) : '';
	}

	/** Un resumen dentro de `$max` caracteres: el principio (el encargo) y lo más reciente, con «[…]» en medio. */
	public static function fit( $text, $max ) {
		$text = (string) $text;
		$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( $len <= $max ) {
			return $text;
		}
		$head = (int) ( $max * 0.3 );
		$tail = max( 0, $max - $head - 5 );
		$sub  = function ( $start, $length ) use ( $text ) {
			return function_exists( 'mb_substr' ) ? mb_substr( $text, $start, $length, 'UTF-8' ) : substr( $text, $start, $length );
		};
		return $sub( 0, $head ) . "\n[…]\n" . $sub( $len - $tail, $tail );
	}

	/**
	 * Los resultados guardados más viejos, compactados cuando todos juntos pasan de STORE_TOOL_BYTES (los de los dos
	 * últimos turnos nunca).
	 */
	public static function compact_stored( array $messages ) {
		$total = 0;
		foreach ( $messages as $m ) {
			if ( 'tool' === ( $m['role'] ?? '' ) ) {
				$total += strlen( (string) ( $m['content'] ?? '' ) );
			}
		}
		if ( $total <= self::STORE_TOOL_BYTES ) {
			return $messages;
		}
		$protect = self::turn_starts( $messages );
		$protect = count( $protect ) >= 2 ? $protect[ count( $protect ) - 2 ] : 0;
		foreach ( $messages as $i => $m ) {
			if ( $total <= self::STORE_TOOL_BYTES || $i >= $protect ) {
				break;
			}
			if ( 'tool' === ( $m['role'] ?? '' ) ) {
				$before                    = strlen( (string) ( $m['content'] ?? '' ) );
				$messages[ $i ]['content'] = self::compact( (string) ( $m['content'] ?? '' ), self::COMPACT_BYTES );
				$total                    -= $before - strlen( $messages[ $i ]['content'] );
			}
		}
		return $messages;
	}

	/** Posiciones donde empieza cada turno (cada mensaje de la persona). */
	private static function turn_starts( array $messages ) {
		$out = array();
		foreach ( $messages as $i => $m ) {
			if ( 'user' === ( $m['role'] ?? '' ) ) {
				$out[] = $i;
			}
		}
		return $out;
	}

	/**
	 * Un resultado de herramienta recortado a `$bytes`: el mismo sobre de dato (con su aviso contra instrucciones
	 * inyectadas), el principio de los datos y la marca «[resultado anterior recortado: N caracteres]». JSON válido.
	 */
	public static function compact( $content, $bytes ) {
		$content = (string) $content;
		if ( strlen( $content ) <= $bytes ) {
			return $content;
		}
		$flags   = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		$decoded = json_decode( $content, true );
		$payload = array( 'tipo' => 'resultado_de_herramienta', 'aviso' => Digitalisimo_AI_Assistant_Tools::DATA_NOTICE );
		$data    = $content;
		if ( is_array( $decoded ) ) {
			foreach ( array( 'herramienta', 'estado', 'error', 'puede_haberse_aplicado' ) as $key ) {
				if ( isset( $decoded[ $key ] ) ) {
					$payload[ $key ] = $decoded[ $key ];
				}
			}
			$data = array_key_exists( 'datos', $decoded ) ? ( is_string( $decoded['datos'] ) ? $decoded['datos'] : (string) wp_json_encode( $decoded['datos'], $flags ) ) : '';
		}
		$length               = function_exists( 'mb_strlen' ) ? mb_strlen( $content, 'UTF-8' ) : strlen( $content );
		$payload['recortado'] = sprintf( '[resultado anterior recortado: %d caracteres]', $length );
		$room                 = max( 0, $bytes - strlen( (string) wp_json_encode( $payload, $flags ) ) - 32 );
		// El tope es del JSON ya codificado: las comillas y barras de `datos` crecen al codificarse, así que se achica
		// hasta que quepa de verdad.
		for ( $try = 0; $try < 12; $try++ ) {
			$payload['datos'] = Digitalisimo_AI_Assistant_Tools::cut_bytes( $data, $room ) . '…';
			$json             = (string) wp_json_encode( $payload, $flags );
			if ( strlen( $json ) <= $bytes || 0 === $room ) {
				break;
			}
			$room = max( 0, $room - ( strlen( $json ) - $bytes ) - 8 );
		}
		return $json;
	}

	/**
	 * Lo que puede medir la petición entera a un modelo (herramientas, instrucciones y mensajes), en caracteres de JSON:
	 * su contexto (CONTEXT_TOKENS) por CONTEXT_SHARE o, si es menor, su tope por precio (PRICE_TOKENS), a
	 * CHARS_PER_TOKEN. for_model() le resta lo que ya ocupan las herramientas y las instrucciones de ese paso. Filtro
	 * `digitalisimo_ai_assistant_context_chars`.
	 */
	public static function context_chars( $provider = '', $model = '' ) {
		$tokens = 0;
		$price  = 0;
		$model  = strtolower( (string) $model );
		foreach ( self::CONTEXT_TOKENS as $prefix => $size ) {
			if ( '' !== $model && 0 === strpos( $model, $prefix ) ) {
				$tokens = (int) ( $size * self::CONTEXT_SHARE );
				break;
			}
		}
		foreach ( self::PRICE_TOKENS as $prefix => $size ) {
			if ( '' !== $model && 0 === strpos( $model, $prefix ) ) {
				$price = $size;
				break;
			}
		}
		if ( $price > 0 ) {
			$tokens = $tokens > 0 ? min( $tokens, $price ) : $price;
		}
		$chars = $tokens ? (int) ( $tokens * self::CHARS_PER_TOKEN ) : self::CONTEXT_DEFAULT;
		return max( 1000, (int) apply_filters( 'digitalisimo_ai_assistant_context_chars', $chars, (string) $provider, (string) $model ) );
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
		// El contenido de un resultado ya viene acotado en bytes y es JSON: recortarlo por caracteres lo rompería.
		foreach ( array( 'text', 'notes' ) as $key ) {
			if ( isset( $fields[ $key ] ) ) {
				$fields[ $key ] = Digitalisimo_AI_Assistant_Tools::clip( (string) $fields[ $key ], self::MAX_TEXT );
			}
		}
		return array_merge( array( 'id' => 'm' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 12 ), 'role' => (string) $role, 'time' => time() ), $fields );
	}

	/** Lo que ve el navegador de un mensaje (nunca el contenido crudo que se manda al modelo). */
	public static function display( array $m ) {
		$role = (string) ( $m['role'] ?? '' );
		$out  = array( 'id' => (string) ( $m['id'] ?? '' ), 'time' => (int) ( $m['time'] ?? 0 ) );
		if ( 'user' === $role ) {
			return $out + array( 'role' => 'me', 'text' => (string) ( $m['text'] ?? '' ) ) + ( empty( $m['files'] ) || ! is_array( $m['files'] ) ? array() : array( 'files' => self::display_files( $m['files'] ) ) );
		}
		if ( 'assistant' === $role ) {
			// Las notas de avance y el texto que acompaña a una llamada son «trabajo»: la pantalla los pliega con las
			// consultas (caso plomerito 2: la persona sólo necesita ver el resultado).
			$notes = trim( (string) ( $m['notes'] ?? '' ) );
			$text  = trim( (string) ( $m['text'] ?? '' ) );
			if ( '' === $notes && '' === $text ) {
				return null;
			}
			return $out + array( 'role' => 'assistant', 'text' => '' !== $text ? $text : $notes, 'notes' => '' !== $text ? $notes : '', 'work' => ! empty( $m['calls'] ) && empty( $m['card'] ), 'provider' => (string) ( $m['provider'] ?? '' ) );
		}
		if ( 'tool' === $role ) {
			return $out + array( 'role' => 'tool', 'ability' => (string) ( $m['ability'] ?? '' ), 'label' => (string) ( $m['label'] ?? '' ), 'kind' => (string) ( $m['kind'] ?? '' ), 'status' => (string) ( $m['status'] ?? '' ), 'summary' => (string) ( $m['summary'] ?? '' ), 'detail' => (string) ( $m['detail'] ?? '' ) );
		}
		if ( 'notice' === $role ) {
			$out += array( 'role' => 'system', 'tone' => (string) ( $m['tone'] ?? 'info' ), 'text' => (string) ( $m['text'] ?? '' ) );
			// Una propuesta de «recordar»: la pantalla pinta la frase con «Sí, recuérdalo» y «No» (assistant-learning.js).
			if ( is_array( $m['memory'] ?? null ) ) {
				$out['memory'] = array( 'id' => (string) ( $m['memory']['id'] ?? '' ), 'text' => (string) ( $m['memory']['text'] ?? '' ), 'replaces' => (string) ( $m['memory']['replaces'] ?? '' ) );
			}
			return $out;
		}
		return null;
	}

	/** Los archivos de un mensaje como los ve la pantalla: nombre, tipo, pesos, miniatura y dirección. */
	private static function display_files( array $files ) {
		$out = array();
		foreach ( $files as $f ) {
			if ( is_array( $f ) && ! empty( $f['id'] ) ) {
				$out[] = array( 'id' => (int) $f['id'], 'name' => (string) ( $f['name'] ?? '' ), 'kind' => 'image' === ( $f['kind'] ?? '' ) ? 'image' : 'file', 'bytes' => (int) ( $f['bytes'] ?? 0 ), 'before' => (int) ( $f['before'] ?? 0 ), 'thumb' => (string) ( $f['thumb'] ?? '' ), 'url' => (string) ( $f['url'] ?? '' ) );
			}
		}
		return $out;
	}

	/** Todos los mensajes visibles de una conversación. */
	public static function display_all( array $conv ) {
		return array_values( array_filter( array_map( array( __CLASS__, 'display' ), $conv['messages'] ) ) );
	}

	/**
	 * Los mensajes para el modelo en formato neutro: la conversación completa mientras quepa en `$budget` caracteres
	 * (context_chars() del modelo si no se da). Si no cabe: primero se compactan los resultados de herramientas de los
	 * turnos anteriores, del más viejo al más nuevo (el turno en curso nunca); después, los turnos más viejos pasan a un
	 * resumen inicial con todo lo que dijeron la persona y el asistente. Siempre empieza en un mensaje de la persona y
	 * cada llamada va seguida de su resultado.
	 *
	 * @param array    $target `provider` y `model` (para el presupuesto).
	 * @param int|null $budget Caracteres para los mensajes; null, los de la petición entera del modelo (context_chars())
	 *                         menos `$fixed`.
	 * @param int      $fixed  Caracteres que ya ocupan las herramientas y las instrucciones de este paso.
	 */
	public static function for_model( array $conv, array $catalog, array $target = array(), $budget = null, $fixed = 0 ) {
		$messages = array();
		$summary  = array();
		foreach ( $conv['messages'] as $m ) {
			$role = (string) ( $m['role'] ?? '' );
			if ( 'summary' === $role ) {
				$summary[] = (string) ( $m['text'] ?? '' );
			} elseif ( 'user' === $role ) {
				// Los archivos que subió con ese mensaje van detrás, como un bloque de datos (nunca instrucciones).
				$files      = ! empty( $m['files'] ) && is_array( $m['files'] ) && class_exists( 'Digitalisimo_AI_Assistant_Uploads' ) ? Digitalisimo_AI_Assistant_Uploads::model_block( $m['files'] ) : '';
				// Lo que escribió la persona nunca pasa por el aviso de archivos subidos (sólo lo pone el servidor).
				$said       = str_replace( '[Archivos que la persona subió', '(texto de la persona que imita el aviso de archivos subidos)', (string) $m['text'] );
				$messages[] = array( 'role' => 'user', 'text' => $said . ( '' !== $files ? "\n\n" . $files : '' ) );
			} elseif ( 'assistant' === $role ) {
				$calls = array();
				foreach ( (array) ( $m['calls'] ?? array() ) as $call ) {
					$calls[] = array( 'id' => (string) $call['id'], 'name' => self::tool_of( $catalog, (string) $call['ability'] ), 'input' => is_array( $call['input'] ?? null ) ? $call['input'] : array(), 'sig' => (string) ( $call['sig'] ?? '' ), 'ability' => (string) $call['ability'] );
				}
				if ( ! $calls && '' === trim( (string) ( $m['text'] ?? '' ) ) ) {
					continue; // Un turno vacío (de una versión anterior) rompería la petición siguiente.
				}
				// Las notas de avance son para la persona: al modelo vuelven sólo dentro de sus bloques thinking.
				$messages[] = array( 'role' => 'assistant', 'text' => (string) ( $m['text'] ?? '' ), 'calls' => $calls, 'thinking' => is_array( $m['thinking'] ?? null ) ? $m['thinking'] : array() );
			} elseif ( 'tool' === $role ) {
				$messages[] = array( 'role' => 'tool', 'call' => (string) $m['call'], 'name' => self::tool_of( $catalog, (string) $m['ability'] ), 'content' => (string) $m['content'] );
			}
		}
		// Lo que quedara antes del primer mensaje de la persona (de una versión anterior) va al resumen, nunca suelto.
		$first = null;
		foreach ( $messages as $i => $m ) {
			if ( 'user' === $m['role'] ) {
				$first = $i;
				break;
			}
		}
		if ( null === $first ) {
			return array();
		}
		if ( $first > 0 ) {
			$summary[] = self::summarize( self::as_stored( array_slice( $messages, 0, $first ) ) );
			$messages  = array_slice( $messages, $first );
		}
		$budget = null === $budget ? max( 2000, self::context_chars( $target['provider'] ?? '', $target['model'] ?? '' ) - max( 0, (int) $fixed ) ) : (int) $budget;
		$size   = function ( array $list ) {
			$n = 0;
			foreach ( $list as $m ) {
				$n += strlen( (string) wp_json_encode( $m ) );
			}
			return $n;
		};
		$starts = self::turn_starts( $messages );
		$last   = end( $starts );
		// 0. Contexto (docs/ASSISTANT.md): los resultados de turnos anteriores van recortados a OLD_RESULT_BYTES y sin los
		// bloques thinking de esos turnos (lo que dijeron la persona y el asistente, y cada llamada, siguen enteros). El
		// recorte es siempre el mismo para un turno ya cerrado: el prefijo sólo cambia, una vez, desde el turno anterior.
		$old_bytes = (int) apply_filters( 'digitalisimo_ai_assistant_old_result_bytes', self::OLD_RESULT_BYTES, $target );
		if ( $old_bytes > 0 ) {
			foreach ( $messages as $i => $m ) {
				if ( $i >= $last ) {
					break;
				}
				if ( 'tool' === $m['role'] ) {
					$messages[ $i ]['content'] = self::compact( $m['content'], max( 400, $old_bytes ) );
				} elseif ( 'assistant' === $m['role'] ) {
					$messages[ $i ]['thinking'] = array();
				}
			}
		}
		$cap    = max( 2000, (int) ( $budget * 0.3 ) );
		$total  = $size( $messages ) + min( $cap, strlen( implode( "\n", $summary ) ) );
		// 1. Resultados de turnos anteriores, del más viejo al más nuevo.
		if ( $total > $budget ) {
			foreach ( $messages as $i => $m ) {
				if ( $total <= $budget || $i >= $last ) {
					break;
				}
				if ( 'tool' === $m['role'] ) {
					$before                    = strlen( (string) wp_json_encode( $m ) );
					$messages[ $i ]['content'] = self::compact( $m['content'], self::COMPACT_BYTES );
					$total                    -= $before - strlen( (string) wp_json_encode( $messages[ $i ] ) );
				}
			}
		}
		// 2. Turnos enteros más viejos al resumen (nunca el turno en curso): se corta de una vez donde ya cabe.
		if ( $total > $budget && count( $starts ) > 1 ) {
			$sizes = array();
			foreach ( $messages as $i => $m ) {
				$sizes[ $i ] = strlen( (string) wp_json_encode( $m ) );
			}
			$cut     = 0;
			$removed = 0;
			$base    = strlen( implode( "\n", $summary ) );
			foreach ( array_slice( $starts, 1 ) as $next ) {
				for ( $i = $cut; $i < $next; $i++ ) {
					$removed += $sizes[ $i ];
					$base    += min( self::SUMMARY_LINE, strlen( (string) ( $messages[ $i ]['text'] ?? '' ) ) ) + 16;
				}
				$cut = $next;
				if ( $total - $removed + min( $cap, $base ) - min( $cap, strlen( implode( "\n", $summary ) ) ) <= $budget ) {
					break;
				}
			}
			$summary[] = self::summarize( self::as_stored( array_slice( $messages, 0, $cut ) ) );
			$messages  = array_slice( $messages, $cut );
		}
		// 3. Si el turno en curso solo no cabe (un turno largo de lecturas grandes), sus resultados más viejos también se
		// recortan, del más viejo al más nuevo; los del último paso (lo que el modelo está por leer) van enteros.
		$total = $size( $messages ) + min( $cap, strlen( implode( "\n", $summary ) ) );
		if ( $total > $budget ) {
			$latest = count( $messages );
			for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
				if ( 'assistant' === $messages[ $i ]['role'] ) {
					$latest = $i;
					break;
				}
			}
			foreach ( $messages as $i => $m ) {
				if ( $total <= $budget || $i >= $latest ) {
					break;
				}
				if ( 'tool' === $m['role'] ) {
					$before                    = strlen( (string) wp_json_encode( $m ) );
					$messages[ $i ]['content'] = self::compact( $m['content'], self::COMPACT_BYTES );
					$total                    -= $before - strlen( (string) wp_json_encode( $messages[ $i ] ) );
				}
			}
		}
		// Bloques thinking: los del turno en curso van siempre tal cual (lo exige la API). Los de turnos anteriores se
		// quitan junto con el recorte de sus resultados (paso 0: el prefijo cambia ahí de todos modos y, si no, la API los
		// descartaría por no cuadrar); sin recorte (filtro de OLD_RESULT_BYTES en 0) van tal cual, como antes
		// (docs/ASSISTANT.md, «Caché»). Filtro `digitalisimo_ai_assistant_replay_thinking`.
		$current = self::turn_starts( $messages );
		$current = $current ? end( $current ) : 0;
		$replay  = (bool) apply_filters( 'digitalisimo_ai_assistant_replay_thinking', $old_bytes <= 0, $target );
		foreach ( $messages as $i => $m ) {
			if ( 'assistant' === $m['role'] ) {
				foreach ( $m['calls'] as $j => $call ) {
					unset( $messages[ $i ]['calls'][ $j ]['ability'] );
				}
				if ( ( $i < $current && ! $replay ) || ! $messages[ $i ]['thinking'] ) {
					unset( $messages[ $i ]['thinking'] );
				}
			}
		}
		$summary = self::fit( trim( implode( "\n", array_filter( $summary, 'strlen' ) ) ), $cap );
		if ( '' !== $summary ) {
			array_unshift( $messages, array( 'role' => 'user', 'text' => "[Resumen de la parte más antigua de esta conversación, que ya no cabe completa. Es lo que dijeron la persona y el asistente; sigue valiendo.]\n" . $summary ) );
		}
		return $messages;
	}

	/** Mensajes neutros como guardados (para summarize()). */
	private static function as_stored( array $messages ) {
		return array_map(
			function ( $m ) {
				if ( 'assistant' === $m['role'] ) {
					$m['calls'] = array_map(
						function ( $c ) {
							return array( 'ability' => $c['ability'] ?? $c['name'] );
						},
						$m['calls']
					);
				}
				return $m;
			},
			$messages
		);
	}

	private static function tool_of( array $catalog, $ability ) {
		return isset( $catalog[ $ability ] ) ? $catalog[ $ability ]['tool'] : Digitalisimo_AI_Assistant_Tools::tool_name( $ability );
	}
}
