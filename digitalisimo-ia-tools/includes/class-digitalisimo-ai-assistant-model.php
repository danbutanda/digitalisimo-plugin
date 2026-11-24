<?php
defined( 'ABSPATH' ) || exit;

/**
 * El modelo del Asistente IA (docs/ASSISTANT.md): la IA y la clave que ya configura IA Tools, con llamadas a
 * herramientas en el formato de cada proveedor (OpenAI y compatibles —DeepSeek, xAI—, Anthropic y Google).
 *
 * - Las IA que se ofrecen salen de los perfiles de uso (menos «images») cuyo proveedor está activo y tiene clave, y de
 *   los proveedores activos con clave. «Auto» es el primer perfil disponible en PROFILE_ORDER (el de contenido, salvo
 *   que exista uno llamado «assistant»). El MODELO del Asistente no es el del perfil: es el de «Modelo del Asistente
 *   IA» en IA Tools → Perfiles de uso (opción `assistant_models`, por proveedor), y por defecto el más capaz con
 *   herramientas (ASSISTANT_MODELS; Anthropic: claude-opus-5-5; decisión del propietario del 2026-11-23: «si gasta
 *   tokens no hay problema»). «Igual que el perfil de uso» vuelve al modelo del perfil. Nunca lo elige quien chatea.
 * - La clave sólo viaja del servidor al proveedor: nada de esta clase la devuelve al navegador. De un error sólo se
 *   muestra el mensaje del proveedor (`error.message`), recortado y sin claves; con 401/403, ni eso.
 * - Si el proveedor rechaza la petición (400) por una herramienta, se quita esa herramienta y se repite (MAX_DROPS).
 * - Los mensajes llegan en un formato neutro (user, assistant con calls, tool con call y content) y aquí se traducen.
 * - `digitalisimo_ai_assistant_pre_model` permite sustituir la llamada (pruebas y proveedores simulados).
 */
final class Digitalisimo_AI_Assistant_Model {
	const PROFILE_ORDER = array( 'assistant', 'content', 'chatbot', 'seo', 'ecommerce', 'automation' );
	const NAMES         = array( 'openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'google' => 'Google', 'xai' => 'xAI', 'deepseek' => 'DeepSeek' );
	const AI_NAMES      = array( 'openai' => 'ChatGPT', 'anthropic' => 'Claude', 'google' => 'Gemini', 'xai' => 'Grok', 'deepseek' => 'DeepSeek' );
	const OPENAI_URLS   = array( 'openai' => 'https://api.openai.com/v1/chat/completions', 'deepseek' => 'https://api.deepseek.com/v1/chat/completions', 'xai' => 'https://api.x.ai/v1/chat/completions' );
	/** Modelo por defecto del Asistente por proveedor: el más capaz con herramientas (el dueño lo puede cambiar). */
	const ASSISTANT_MODELS = array( 'anthropic' => 'claude-opus-5-5', 'openai' => 'gpt-4.1', 'google' => 'gemini-2.5-pro', 'xai' => 'grok-4', 'deepseek' => 'deepseek-chat' );
	/** Modelos que se sugieren en el selector (además de escribir otro). */
	const ASSISTANT_CHOICES = array(
		'anthropic' => array( 'claude-opus-5-5' => 'Claude Opus 5.5 · el más capaz (recomendado; cuesta el doble que Sonnet 5.5)', 'claude-sonnet-5-5' => 'Claude Sonnet 5.5 · más económico', 'claude-haiku-5-5' => 'Claude Haiku 5.5 · el más barato, menos capaz' ),
		'openai'    => array( 'gpt-4.1' => 'GPT-4.1 · el más capaz con herramientas (recomendado)', 'gpt-4o-mini' => 'GPT-4o mini · más económico' ),
		'google'    => array( 'gemini-2.5-pro' => 'Gemini 2.5 Pro · el más capaz (recomendado)', 'gemini-2.5-flash' => 'Gemini 2.5 Flash · más económico' ),
		'xai'       => array( 'grok-4' => 'Grok 4 · el más capaz (recomendado)', 'grok-3-mini' => 'Grok 3 mini · más económico' ),
		'deepseek'  => array( 'deepseek-chat' => 'DeepSeek Chat (recomendado)' ),
	);
	/**
	 * Modelos de Anthropic que escriben sus notas entre llamadas a herramientas como bloques `thinking` (vacíos salvo
	 * con `display: "updates"`, beta UPDATES_BETA): sin pedirlas, un turno que termina en una herramienta llega sin texto.
	 */
	const UPDATES_MODELS = '/^claude-(opus-5-5|sonnet-5-5|haiku-5-5|fable-5|mythos-5)/';
	const UPDATES_BETA   = 'thinking-display-updates-2026-08-18';
	/** Texto con el que la API marca una nota de avance cortada (no es para la persona). */
	const UPDATES_CUT    = 'This part of the response was interrupted before it finished.';
	/** Con razonamiento adaptativo, 16 384 tokens dejan sitio a pensar y a una composición larga. */
	const MAX_TOKENS    = 16384;
	const MIN_TOKENS    = 4096;
	/** Sin flujo, 16 384 tokens de salida a 60-80 por segundo tardan hasta unos 270 s: la espera tiene que cubrirlos. */
	const TIMEOUT       = 300;
	/** Herramientas que se pueden quitar, una por reintento, cuando el proveedor rechaza la petición por una de ellas. */
	const MAX_DROPS     = 3;
	/** Herramientas rechazadas por cada proveedor (transient de un día): `proveedor` => `nombre|md5 del esquema` => motivo. */
	const REJECTED      = 'digitalisimo_ai_assistant_rejected_tools';

	/**
	 * Las IA disponibles, por proveedor: `provider`, `model`, `name`, `ai_name` y `profile`. Sin claves.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function targets() {
		$o         = Digitalisimo_AI::get();
		$profiles  = is_array( $o['profiles'] ?? null ) ? $o['profiles'] : array();
		$providers = is_array( $o['providers'] ?? null ) ? $o['providers'] : array();
		$out       = array();
		foreach ( array_unique( array_merge( self::PROFILE_ORDER, array_keys( $profiles ) ) ) as $profile ) {
			if ( 'images' === $profile || empty( $profiles[ $profile ] ) || ! is_array( $profiles[ $profile ] ) ) {
				continue;
			}
			$id    = sanitize_key( (string) ( $profiles[ $profile ]['provider'] ?? '' ) );
			$model = trim( (string) ( $profiles[ $profile ]['model'] ?? '' ) );
			if ( ! isset( self::NAMES[ $id ] ) || '' === $model || isset( $out[ $id ] ) || empty( $providers[ $id ]['enabled'] ) || empty( $providers[ $id ]['key'] ) ) {
				continue;
			}
			$out[ $id ] = array( 'provider' => $id, 'model' => self::assistant_model( $id, $model, 'assistant' === $profile, $o ), 'name' => self::NAMES[ $id ], 'ai_name' => self::AI_NAMES[ $id ], 'profile' => (string) $profile );
		}
		// Un proveedor activo con clave que ningún perfil eligió también está disponible, con su modelo recomendado
		// (si no, activar sólo Anthropic dejaba el Asistente en «No hay ninguna IA configurada»).
		foreach ( Digitalisimo_AI::usable_providers( $o ) as $id => $model ) {
			if ( isset( self::NAMES[ $id ] ) && ! isset( $out[ $id ] ) ) {
				$out[ $id ] = array( 'provider' => $id, 'model' => self::assistant_model( $id, $model, false, $o ), 'name' => self::NAMES[ $id ], 'ai_name' => self::AI_NAMES[ $id ], 'profile' => '' );
			}
		}
		return $out;
	}

	/**
	 * El modelo del Asistente para un proveedor: lo elegido en «Modelo del Asistente IA» (`assistant_models`); vacío,
	 * el de un perfil llamado «assistant» si lo hay, y si no el recomendado (ASSISTANT_MODELS, filtro
	 * `digitalisimo_ai_assistant_default_model`); «profile», el del perfil de uso (`$profile_model`).
	 */
	public static function assistant_model( $id, $profile_model, $assistant_profile = false, $o = null ) {
		$o      = is_array( $o ) ? $o : Digitalisimo_AI::get();
		$chosen = trim( (string) ( ( is_array( $o['assistant_models'] ?? null ) ? $o['assistant_models'] : array() )[ $id ] ?? '' ) );
		if ( 'profile' === $chosen ) {
			return (string) $profile_model;
		}
		if ( '' !== $chosen ) {
			return $chosen;
		}
		if ( $assistant_profile && '' !== trim( (string) $profile_model ) ) {
			return (string) $profile_model;
		}
		$recommended = (string) apply_filters( 'digitalisimo_ai_assistant_default_model', self::ASSISTANT_MODELS[ $id ] ?? '', $id );
		return '' !== $recommended ? $recommended : (string) $profile_model;
	}

	/** La IA para una conversación (`auto` o la clave de un proveedor), con su clave y su URL: sólo para el servidor. */
	public static function target( $choice ) {
		$targets = self::targets();
		if ( ! $targets ) {
			return new WP_Error( 'digitalisimo_assistant_no_ai', 'No hay ninguna IA configurada: activa un proveedor con su clave en IA Tools → Proveedores.', array( 'status' => 409 ) );
		}
		$choice = sanitize_key( (string) $choice );
		if ( '' === $choice || 'auto' === $choice ) {
			$target = reset( $targets );
		} elseif ( isset( $targets[ $choice ] ) ) {
			$target = $targets[ $choice ];
		} else {
			return new WP_Error( 'digitalisimo_assistant_ai', 'Esa IA no está disponible en este sitio.', array( 'status' => 400 ) );
		}
		$providers     = (array) Digitalisimo_AI::get( 'providers' );
		$target['key'] = (string) ( $providers[ $target['provider'] ]['key'] ?? '' );
		$target['url'] = (string) ( $providers[ $target['provider'] ]['url'] ?? '' );
		return $target;
	}

	/**
	 * Un paso de la conversación: `array( 'text' => string, 'calls' => array( array( 'id', 'name', 'input', 'sig' ) ) )`.
	 *
	 * @param array  $target   De target().
	 * @param string $system   Instrucciones del sistema.
	 * @param array  $messages Formato neutro.
	 * @param array  $tools    `name`, `description` y `schema` de cada herramienta.
	 * @return array|WP_Error
	 */
	public static function chat( array $target, $system, array $messages, array $tools ) {
		$pre = apply_filters( 'digitalisimo_ai_assistant_pre_model', null, array( 'provider' => $target['provider'], 'model' => $target['model'], 'system' => $system, 'messages' => $messages, 'tools' => $tools ) );
		if ( null !== $pre ) {
			return self::normalize( $pre );
		}
		$timeout = max( 10, (int) apply_filters( 'digitalisimo_ai_assistant_timeout', self::TIMEOUT ) );
		$tools   = self::usable_tools( $target['provider'], $tools );
		$dropped = 0;
		for ( $try = 0; $try < 2 + self::MAX_DROPS; $try++ ) {
			$request = self::build( $target, $system, $messages, $tools );
			if ( is_wp_error( $request ) ) {
				return $request;
			}
			$response = wp_remote_post( $request['url'], array( 'timeout' => $timeout, 'headers' => $request['headers'], 'body' => wp_json_encode( $request['body'] ) ) );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( 400 !== $code ) {
				break;
			}
			$raw    = (string) wp_remote_retrieve_body( $response );
			$reason = self::error_reason( $raw );
			$index  = self::rejected_tool( $target['provider'], $reason . ' ' . $raw );
			// Si la API no acepta las notas de avance (`display: "updates"` o su beta), se repite una vez sin ellas.
			if ( null === $index && 'anthropic' === $target['provider'] && empty( $target['no_updates'] ) && ! empty( $request['body']['thinking'] ) && preg_match( '/thinking|display|beta/i', $raw ) ) {
				$target['no_updates'] = true;
				continue;
			}
			// Un modelo de Anthropic con tope de salida menor (4 096) rechaza 8 192: se repite una vez con su tope.
			if ( null === $index && 'anthropic' === $target['provider'] && empty( $target['max_tokens'] ) && false !== stripos( $raw, 'max_tokens' ) ) {
				$target['max_tokens'] = self::MIN_TOKENS;
				continue;
			}
			// Una herramienta que el proveedor rechaza (su esquema, su nombre) tumba toda la petición: se quita esa y se
			// repite, hasta MAX_DROPS veces. Queda anotada un día (por proveedor y esquema) para no volver a mandarla.
			if ( null === $index || $dropped >= self::MAX_DROPS || ! isset( $request['tool_names'][ $index ] ) ) {
				break;
			}
			$tools = self::drop_tool( $target['provider'], $tools, $request['tool_names'][ $index ], $reason );
			$dropped++;
		}
		if ( is_wp_error( $response ) || $code < 200 || $code >= 300 ) {
			Digitalisimo_AI::log( 'assistant', $target['provider'], false, $code );
			$ai = self::AI_NAMES[ $target['provider'] ] ?? $target['provider'];
			if ( 0 === $code ) {
				$why = 'sin conexión con el proveedor';
			} elseif ( in_array( $code, array( 401, 403 ), true ) ) {
				$why = 'HTTP ' . $code . ': revisa la clave en IA Tools';
			} else {
				$reason = self::error_reason( (string) wp_remote_retrieve_body( $response ) );
				$why    = 'HTTP ' . $code . ( '' !== $reason ? ': ' . $reason : '' );
			}
			return new WP_Error( 'digitalisimo_assistant_provider', sprintf( '%s no respondió (%s).', $ai, $why ), array( 'status' => 502 ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		Digitalisimo_AI::log( 'assistant', $target['provider'], true, $code );
		return self::parse( $target['provider'], is_array( $data ) ? $data : array() );
	}

	/**
	 * El motivo que da el proveedor en un error (`error.message` de Anthropic, OpenAI y Google), en una línea, sin claves
	 * ni etiquetas y recortado a 200 caracteres; '' si no hay.
	 */
	public static function error_reason( $body ) {
		$data    = json_decode( (string) $body, true );
		$data    = is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ? $data[0] : $data; // Google a veces manda una lista.
		$error   = is_array( $data ) && array_key_exists( 'error', $data ) ? $data['error'] : null;
		$message = is_array( $error ) && is_string( $error['message'] ?? null ) ? $error['message'] : '';
		$message = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $message ) ) );
		return '' === $message ? '' : Digitalisimo_AI_Assistant_Tools::clip( Digitalisimo_AI_Assistant_Tools::scrub( $message ), 200 );
	}

	/**
	 * La posición de la herramienta que el proveedor señala en su error, o null: Anthropic `tools.12.…`, OpenAI
	 * `tools[3].…` y Google `function_declarations[3]` / `functionDeclarations[3]`.
	 */
	public static function rejected_tool( $provider, $reason ) {
		$reason = (string) $reason;
		if ( 'anthropic' === $provider && preg_match( '/\btools\.(\d+)\b/', $reason, $m ) ) {
			return (int) $m[1];
		}
		if ( isset( self::OPENAI_URLS[ $provider ] ) && preg_match( '/\btools\[(\d+)\]/', $reason, $m ) ) {
			return (int) $m[1];
		}
		if ( 'google' === $provider && preg_match( '/function_?declarations\[(\d+)\]/i', $reason, $m ) ) {
			return (int) $m[1];
		}
		return null;
	}

	/** Quita una herramienta rechazada, la anota (un día) y lo deja en la bitácora del Asistente. */
	private static function drop_tool( $provider, array $tools, $name, $reason ) {
		$out = array();
		foreach ( $tools as $tool ) {
			if ( $tool['name'] === $name ) {
				$rejected = get_transient( self::REJECTED );
				$rejected = is_array( $rejected ) ? $rejected : array();
				$rejected[ $provider ][ self::tool_key( $tool ) ] = (string) $reason;
				set_transient( self::REJECTED, $rejected, DAY_IN_SECONDS );
				continue;
			}
			$out[] = $tool;
		}
		Digitalisimo_AI_Assistant_Tools::log( array( 'user' => get_current_user_id(), 'ability' => $name, 'kind' => 'rejected', 'input' => '', 'result' => 'skipped', 'detail' => sprintf( '%s rechazó la herramienta y se quitó de la petición: %s', self::AI_NAMES[ $provider ] ?? $provider, $reason ) ) );
		do_action( 'digitalisimo_ai_assistant_tool_rejected', $name, $provider, $reason );
		return $out;
	}

	/** Las herramientas sin las que este proveedor ya rechazó (con el mismo esquema) en el último día. */
	private static function usable_tools( $provider, array $tools ) {
		$rejected = get_transient( self::REJECTED );
		$rejected = is_array( $rejected ) && is_array( $rejected[ $provider ] ?? null ) ? $rejected[ $provider ] : array();
		if ( ! $rejected ) {
			return $tools;
		}
		return array_values(
			array_filter(
				$tools,
				function ( $tool ) use ( $rejected ) {
					return ! isset( $rejected[ self::tool_key( $tool ) ] );
				}
			)
		);
	}

	private static function tool_key( array $tool ) {
		return $tool['name'] . '|' . md5( (string) wp_json_encode( $tool['schema'] ?? null ) );
	}

	/**
	 * URL, cabeceras y cuerpo de la petición al proveedor (pura: la prueba la lee sin red), y `tool_names`: el nombre
	 * de cada herramienta en el orden del cuerpo (los errores del proveedor la señalan por su posición).
	 */
	public static function build( array $target, $system, array $messages, array $tools ) {
		$id    = $target['provider'];
		$names = array();
		$tools = array_values(
			array_filter(
				$tools,
				function ( $tool ) use ( &$names ) {
					// Dos herramientas con el mismo nombre hacen que el proveedor rechace la petición entera.
					if ( isset( $names[ $tool['name'] ] ) ) {
						return false;
					}
					$names[ $tool['name'] ] = true;
					return true;
				}
			)
		);
		$names = array_keys( $names );
		$key = (string) ( $target['key'] ?? '' );
		$url = (string) ( $target['url'] ?? '' );
		if ( isset( self::OPENAI_URLS[ $id ] ) ) {
			$body = array( 'model' => $target['model'], 'messages' => self::openai_messages( $system, $messages ) );
			if ( $tools ) {
				$body['tools'] = array();
				foreach ( $tools as $tool ) {
					$body['tools'][] = array( 'type' => 'function', 'function' => array( 'name' => $tool['name'], 'description' => $tool['description'], 'parameters' => self::object_schema( $tool['schema'] ) ) );
				}
			}
			return array( 'url' => $url ?: self::OPENAI_URLS[ $id ], 'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key ), 'body' => $body, 'tool_names' => $names );
		}
		if ( 'anthropic' === $id ) {
			$body = array( 'model' => $target['model'], 'max_tokens' => (int) ( $target['max_tokens'] ?? self::MAX_TOKENS ), 'system' => $system, 'messages' => self::anthropic_messages( $messages ) );
			// Caché automática de la API de Anthropic: cada paso de un turno reenvía la conversación entera (la memoria
			// completa) y sólo crece al final, así que lo ya enviado se lee de la caché. No con una URL compatible propia.
			if ( '' === $url && (bool) apply_filters( 'digitalisimo_ai_assistant_prompt_cache', true, $target ) ) {
				$body['cache_control'] = array( 'type' => 'ephemeral' );
			}
			if ( $tools ) {
				$body['tools'] = array();
				foreach ( $tools as $tool ) {
					$body['tools'][] = array( 'name' => $tool['name'], 'description' => $tool['description'], 'input_schema' => self::object_schema( $tool['schema'] ) );
				}
			}
			$headers = array( 'Content-Type' => 'application/json', 'x-api-key' => $key, 'anthropic-version' => '2023-06-01' );
			// Las notas que el modelo escribe entre herramientas («Creo la landing…», «Verifico…») llegan como texto.
			if ( empty( $target['no_updates'] ) && preg_match( self::UPDATES_MODELS, (string) $target['model'] ) && (bool) apply_filters( 'digitalisimo_ai_assistant_progress_updates', true, $target ) ) {
				$body['thinking']          = array( 'type' => 'adaptive', 'display' => 'updates' );
				$headers['anthropic-beta'] = self::UPDATES_BETA;
			}
			return array( 'url' => $url ?: 'https://api.anthropic.com/v1/messages', 'headers' => $headers, 'body' => $body, 'tool_names' => $names );
		}
		if ( 'google' === $id ) {
			$body = array( 'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ), 'contents' => self::google_messages( $messages ) );
			if ( $tools ) {
				$declarations = array();
				foreach ( $tools as $tool ) {
					$declaration = array( 'name' => $tool['name'], 'description' => $tool['description'] );
					$parameters  = self::google_schema( self::object_schema( $tool['schema'] ), true );
					if ( null !== $parameters ) {
						$declaration['parameters'] = $parameters;
					}
					$declarations[] = $declaration;
				}
				$body['tools'] = array( array( 'functionDeclarations' => $declarations ) );
			}
			// La clave va en cabecera, no en la URL (que puede acabar en un registro de errores).
			return array( 'url' => $url ?: 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $target['model'] ) . ':generateContent', 'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => $key ), 'body' => $body, 'tool_names' => $names );
		}
		return new WP_Error( 'digitalisimo_assistant_ai', 'Proveedor no compatible.', array( 'status' => 400 ) );
	}

	/** Respuesta del proveedor al formato neutro. */
	public static function parse( $provider, array $data ) {
		$text      = '';
		$calls     = array();
		$refusal   = '';
		$truncated = false;
		if ( isset( self::OPENAI_URLS[ $provider ] ) ) {
			$message   = (array) ( $data['choices'][0]['message'] ?? array() );
			$text      = is_string( $message['content'] ?? null ) ? $message['content'] : '';
			$refusal   = is_string( $message['refusal'] ?? null ) ? $message['refusal'] : '';
			$truncated = 'length' === ( $data['choices'][0]['finish_reason'] ?? '' );
			foreach ( (array) ( $message['tool_calls'] ?? array() ) as $call ) {
				$input   = json_decode( (string) ( $call['function']['arguments'] ?? '' ), true );
				$calls[] = array( 'id' => (string) ( $call['id'] ?? '' ), 'name' => (string) ( $call['function']['name'] ?? '' ), 'input' => is_array( $input ) ? $input : array() );
			}
		} elseif ( 'anthropic' === $provider ) {
			$truncated = 'max_tokens' === ( $data['stop_reason'] ?? '' );
			$refusal   = 'refusal' === ( $data['stop_reason'] ?? '' ) ? 'La IA se negó a responder.' : '';
			foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
				if ( 'text' === ( $block['type'] ?? '' ) ) {
					$text .= (string) ( $block['text'] ?? '' );
				} elseif ( 'thinking' === ( $block['type'] ?? '' ) && '' !== trim( (string) ( $block['thinking'] ?? '' ) ) && self::UPDATES_CUT !== trim( (string) $block['thinking'] ) ) {
					// Con `display: "updates"`, un bloque thinking con texto es una nota de avance para la persona.
					$text .= ( '' !== $text ? "\n\n" : '' ) . trim( (string) $block['thinking'] );
				} elseif ( 'tool_use' === ( $block['type'] ?? '' ) ) {
					$calls[] = array( 'id' => (string) ( $block['id'] ?? '' ), 'name' => (string) ( $block['name'] ?? '' ), 'input' => is_array( $block['input'] ?? null ) ? $block['input'] : array() );
				}
			}
		} elseif ( 'google' === $provider ) {
			$truncated = 'MAX_TOKENS' === ( $data['candidates'][0]['finishReason'] ?? '' );
			foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
				if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
					$text .= (string) $part['text'];
				}
				if ( isset( $part['functionCall'] ) ) {
					$calls[] = array( 'id' => (string) ( $part['functionCall']['id'] ?? '' ), 'name' => (string) ( $part['functionCall']['name'] ?? '' ), 'input' => is_array( $part['functionCall']['args'] ?? null ) ? $part['functionCall']['args'] : array(), 'sig' => (string) ( $part['thoughtSignature'] ?? '' ) );
				}
			}
		}
		return self::normalize( array( 'text' => $text, 'calls' => $calls, 'refusal' => $refusal, 'truncated' => $truncated ) );
	}

	/** Respuesta (de un proveedor o de un simulador) con su forma garantizada e IDs de llamada válidos y únicos. */
	public static function normalize( $reply ) {
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		if ( is_string( $reply ) ) {
			$reply = array( 'text' => $reply );
		}
		$reply = is_array( $reply ) ? $reply : array();
		$calls = array();
		$seen  = array();
		foreach ( (array) ( $reply['calls'] ?? array() ) as $call ) {
			if ( ! is_array( $call ) || '' === (string) ( $call['name'] ?? '' ) ) {
				continue;
			}
			$id = (string) ( $call['id'] ?? '' );
			if ( ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $id ) || isset( $seen[ $id ] ) ) {
				$id = 'call_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 16 );
			}
			$seen[ $id ] = true;
			$calls[]     = array( 'id' => $id, 'name' => substr( (string) $call['name'], 0, 128 ), 'input' => is_array( $call['input'] ?? null ) ? $call['input'] : array(), 'sig' => substr( (string) ( $call['sig'] ?? '' ), 0, 8192 ) );
		}
		return array( 'text' => is_string( $reply['text'] ?? null ) ? $reply['text'] : '', 'calls' => $calls, 'refusal' => is_string( $reply['refusal'] ?? null ) ? $reply['refusal'] : '', 'truncated' => ! empty( $reply['truncated'] ) );
	}

	private static function openai_messages( $system, array $messages ) {
		$out = array( array( 'role' => 'system', 'content' => (string) $system ) );
		foreach ( $messages as $m ) {
			if ( 'user' === $m['role'] ) {
				$out[] = array( 'role' => 'user', 'content' => (string) $m['text'] );
			} elseif ( 'assistant' === $m['role'] ) {
				if ( '' === (string) $m['text'] && empty( $m['calls'] ) ) {
					continue; // La API rechaza un assistant sin contenido ni llamadas.
				}
				$row = array( 'role' => 'assistant', 'content' => '' !== (string) $m['text'] ? (string) $m['text'] : null );
				foreach ( (array) ( $m['calls'] ?? array() ) as $call ) {
					$row['tool_calls'][] = array( 'id' => $call['id'], 'type' => 'function', 'function' => array( 'name' => $call['name'], 'arguments' => wp_json_encode( self::object( $call['input'] ) ) ) );
				}
				$out[] = $row;
			} elseif ( 'tool' === $m['role'] ) {
				$out[] = array( 'role' => 'tool', 'tool_call_id' => $m['call'], 'content' => (string) $m['content'] );
			}
		}
		return $out;
	}

	/** Anthropic: los resultados van en un mensaje del usuario y dos mensajes seguidos del mismo rol se unen. */
	private static function anthropic_messages( array $messages ) {
		$out = array();
		foreach ( $messages as $m ) {
			$blocks = array();
			if ( 'user' === $m['role'] ) {
				$role     = 'user';
				$blocks[] = array( 'type' => 'text', 'text' => (string) $m['text'] );
			} elseif ( 'assistant' === $m['role'] ) {
				$role = 'assistant';
				if ( '' !== trim( (string) $m['text'] ) ) {
					$blocks[] = array( 'type' => 'text', 'text' => (string) $m['text'] );
				}
				foreach ( (array) ( $m['calls'] ?? array() ) as $call ) {
					$blocks[] = array( 'type' => 'tool_use', 'id' => $call['id'], 'name' => $call['name'], 'input' => self::object( $call['input'] ) );
				}
			} elseif ( 'tool' === $m['role'] ) {
				$role     = 'user';
				$blocks[] = array( 'type' => 'tool_result', 'tool_use_id' => $m['call'], 'content' => (string) $m['content'] );
			} else {
				continue;
			}
			$out = self::merge( $out, $role, 'content', $blocks );
		}
		return $out;
	}

	/** Google: el modelo es «model» y cada respuesta de función va en un turno del usuario, con su nombre. */
	private static function google_messages( array $messages ) {
		$out = array();
		foreach ( $messages as $m ) {
			$parts = array();
			if ( 'user' === $m['role'] ) {
				$role    = 'user';
				$parts[] = array( 'text' => (string) $m['text'] );
			} elseif ( 'assistant' === $m['role'] ) {
				$role = 'model';
				if ( '' !== trim( (string) $m['text'] ) ) {
					$parts[] = array( 'text' => (string) $m['text'] );
				}
				foreach ( (array) ( $m['calls'] ?? array() ) as $call ) {
					$part = array( 'functionCall' => array( 'name' => $call['name'], 'args' => self::object( $call['input'] ) ) );
					if ( '' !== (string) ( $call['sig'] ?? '' ) ) {
						$part['thoughtSignature'] = $call['sig'];
					}
					$parts[] = $part;
				}
			} elseif ( 'tool' === $m['role'] ) {
				$role    = 'user';
				$decoded = json_decode( (string) $m['content'], true );
				$parts[] = array( 'functionResponse' => array( 'name' => $m['name'], 'response' => is_array( $decoded ) ? $decoded : array( 'contenido' => (string) $m['content'] ) ) );
			} else {
				continue;
			}
			$out = self::merge( $out, $role, 'parts', $parts );
		}
		return $out;
	}

	private static function merge( array $out, $role, $key, array $items ) {
		if ( ! $items ) {
			return $out;
		}
		$last = count( $out ) - 1;
		if ( $last >= 0 && $out[ $last ]['role'] === $role ) {
			$out[ $last ][ $key ] = array_merge( $out[ $last ][ $key ], $items );
		} else {
			$out[] = array( 'role' => $role, $key => $items );
		}
		return $out;
	}

	/** Un objeto JSON aunque esté vacío (un array vacío de PHP saldría como `[]`). */
	private static function object( $value ) {
		return is_array( $value ) && $value ? $value : new stdClass();
	}

	/**
	 * Esquema de entrada como objeto JSON válido para los tres formatos (JSON Schema 2020-12 y las reglas de cada API:
	 * Anthropic rechaza con un 400 toda la petición si UNA herramienta trae un esquema inválido). Se sanea lo que las
	 * Abilities heredan de los esquemas REST de WordPress o de draft-04: `required: true` dentro de la propiedad pasa a
	 * la lista `required` del padre; `items` o `additionalProperties` vacíos o como lista, a `{}`; se quitan `context`,
	 * `readonly`, `arg_options`, los callbacks, `$ref` sin resolver, `enum` vacío, tipos inexistentes, límites que no
	 * son números (`exclusiveMinimum: true` pasa a número), patrones que no compilan y propiedades con nombres que la
	 * API no admite (^[a-zA-Z0-9_.-]{1,64}$). La raíz nunca lleva oneOf/anyOf/allOf: sus propiedades se funden.
	 */
	public static function object_schema( $schema ) {
		$schema = is_array( $schema ) ? $schema : array();
		if ( 'object' !== ( $schema['type'] ?? 'object' ) ) {
			$schema = array( 'type' => 'object', 'properties' => array( 'value' => $schema ), 'required' => array( 'value' ) );
		}
		$schema = self::flatten_root( $schema );
		$schema = self::clean_schema( $schema, 0 );
		$schema = is_array( $schema ) ? $schema : array();
		$schema['type'] = 'object';
		if ( ! isset( $schema['properties'] ) ) {
			$schema['properties'] = new stdClass();
		}
		return $schema;
	}

	const SCHEMA_TYPES = array( 'object', 'array', 'string', 'number', 'integer', 'boolean', 'null' );
	const TYPE_ALIASES = array( 'int' => 'integer', 'float' => 'number', 'double' => 'number', 'bool' => 'boolean', 'text' => 'string' );
	/** Claves de los esquemas REST de WordPress que no son JSON Schema. */
	const WP_KEYS = array( 'context', 'readonly', 'readOnly', 'arg_options', 'sanitize_callback', 'validate_callback', '$ref', '$schema', '$id', '$defs', 'definitions', '$comment', 'ignore' );

	/**
	 * oneOf/anyOf/allOf en la raíz (Anthropic y OpenAI lo rechazan; WooCommerce 11 lo usa en product-create y
	 * product-update): las propiedades de las ramas se funden en la raíz, con los `enum` de una misma propiedad unidos.
	 * Requeridas quedan las de allOf y las que piden TODAS las ramas de anyOf/oneOf. La Ability valida después la entrada
	 * contra su esquema original, así que una combinación que ninguna rama admite sigue rechazándose.
	 */
	private static function flatten_root( array $schema ) {
		foreach ( array( 'allOf', 'anyOf', 'oneOf' ) as $union ) {
			if ( ! array_key_exists( $union, $schema ) ) {
				continue;
			}
			$branches = array_values( array_filter( is_array( $schema[ $union ] ) ? $schema[ $union ] : array(), 'is_array' ) );
			unset( $schema[ $union ] );
			$props    = is_array( $schema['properties'] ?? null ) ? $schema['properties'] : array();
			$required = is_array( $schema['required'] ?? null ) ? $schema['required'] : array();
			$common   = null;
			foreach ( $branches as $branch ) {
				foreach ( ( is_array( $branch['properties'] ?? null ) ? $branch['properties'] : array() ) as $name => $sub ) {
					if ( ! isset( $props[ $name ] ) ) {
						$props[ $name ] = $sub;
					} elseif ( is_array( $props[ $name ]['enum'] ?? null ) && is_array( $sub['enum'] ?? null ) ) {
						$props[ $name ]['enum'] = array_values( array_unique( array_merge( $props[ $name ]['enum'], $sub['enum'] ), SORT_REGULAR ) );
						unset( $props[ $name ]['default'] );
					}
				}
				$branch_required = is_array( $branch['required'] ?? null ) ? $branch['required'] : array();
				if ( 'allOf' === $union ) {
					$required = array_merge( $required, $branch_required );
				} else {
					$common = null === $common ? $branch_required : array_values( array_intersect( $common, $branch_required ) );
				}
			}
			$required = array_merge( $required, (array) $common );
			$schema['properties'] = $props;
			if ( $required ) {
				$schema['required'] = $required;
			}
		}
		return $schema;
	}

	/**
	 * Un subesquema válido (o null si no se puede salvar). Los booleanos se conservan donde JSON Schema los admite.
	 *
	 * @return array|stdClass|bool|null
	 */
	private static function clean_schema( $schema, $depth ) {
		if ( is_bool( $schema ) ) {
			return $schema;
		}
		if ( is_object( $schema ) ) {
			$schema = json_decode( (string) wp_json_encode( $schema ), true );
		}
		if ( ! is_array( $schema ) || $depth > 32 ) {
			return null;
		}
		if ( ! $schema ) {
			return new stdClass();
		}
		if ( self::is_list( $schema ) ) {
			// Una lista donde va un esquema (items de draft-04 en forma de tupla): vale la primera.
			return self::clean_schema( $schema[0], $depth );
		}
		$out = array();
		foreach ( $schema as $key => $value ) {
			$key = (string) $key;
			if ( is_object( $value ) ) {
				// Restos de un json_decode en objetos (properties, enum…): como arreglos, igual que el adaptador MCP.
				$value = json_decode( (string) wp_json_encode( $value ), true );
			}
			if ( in_array( $key, self::WP_KEYS, true ) || 'required' === $key && ! is_array( $value ) ) {
				continue;
			}
			switch ( $key ) {
				case 'type':
					$types = array();
					foreach ( (array) $value as $type ) {
						$type = is_string( $type ) ? strtolower( $type ) : '';
						$type = self::TYPE_ALIASES[ $type ] ?? $type;
						if ( in_array( $type, self::SCHEMA_TYPES, true ) && ! in_array( $type, $types, true ) ) {
							$types[] = $type;
						}
					}
					if ( $types ) {
						$out['type'] = is_array( $value ) ? $types : $types[0];
					}
					break;
				case 'properties':
					$props    = array();
					$required = array();
					foreach ( ( is_array( $value ) ? $value : array() ) as $name => $sub ) {
						$name = (string) $name;
						if ( ! preg_match( '/^[a-zA-Z0-9_.-]{1,64}$/', $name ) ) {
							continue;
						}
						if ( is_array( $sub ) && true === ( $sub['required'] ?? null ) ) {
							$required[] = $name;
						}
						$sub = self::clean_schema( $sub, $depth + 1 );
						if ( null !== $sub ) {
							$props[ $name ] = $sub;
						}
					}
					$out['properties'] = $props ? $props : new stdClass();
					if ( $required ) {
						$out['required'] = array_merge( is_array( $out['required'] ?? null ) ? $out['required'] : array(), $required );
					}
					break;
				case 'required':
					$out['required'] = array_merge( $value, is_array( $out['required'] ?? null ) ? $out['required'] : array() );
					break;
				case 'items':
				case 'additionalProperties':
				case 'not':
				case 'if':
				case 'then':
				case 'else':
				case 'contains':
				case 'propertyNames':
				case 'unevaluatedItems':
				case 'unevaluatedProperties':
					$sub = self::clean_schema( $value, $depth + 1 );
					if ( null !== $sub ) {
						$out[ $key ] = $sub;
					} elseif ( 'items' === $key ) {
						$out['items'] = new stdClass();
					}
					break;
				case 'patternProperties':
				case 'dependentSchemas':
					$map = array();
					foreach ( ( is_array( $value ) ? $value : array() ) as $name => $sub ) {
						if ( 'patternProperties' === $key && ! self::valid_pattern( (string) $name ) ) {
							continue;
						}
						$sub = self::clean_schema( $sub, $depth + 1 );
						if ( null !== $sub ) {
							$map[ (string) $name ] = $sub;
						}
					}
					if ( $map ) {
						$out[ $key ] = $map;
					}
					break;
				case 'anyOf':
				case 'oneOf':
				case 'allOf':
				case 'prefixItems':
					$list = array();
					foreach ( ( is_array( $value ) ? $value : array() ) as $sub ) {
						$sub = self::clean_schema( $sub, $depth + 1 );
						if ( null !== $sub ) {
							$list[] = $sub;
						}
					}
					if ( $list ) {
						$out[ $key ] = $list;
					}
					break;
				case 'enum':
					if ( is_array( $value ) && $value ) {
						$out['enum'] = array_values( $value );
					}
					break;
				case 'minimum':
				case 'maximum':
				case 'multipleOf':
					if ( is_int( $value ) || is_float( $value ) || is_numeric( $value ) ) {
						$out[ $key ] = $value + 0;
					}
					break;
				case 'exclusiveMinimum':
				case 'exclusiveMaximum':
					if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
						$out[ $key ] = $value + 0;
					} elseif ( true === $value ) {
						// draft-04: el booleano marca exclusivo el mínimo o máximo de al lado.
						$limit = 'exclusiveMinimum' === $key ? 'minimum' : 'maximum';
						if ( isset( $schema[ $limit ] ) && is_numeric( $schema[ $limit ] ) ) {
							$out[ $key ] = $schema[ $limit ] + 0;
						}
					}
					break;
				case 'minLength':
				case 'maxLength':
				case 'minItems':
				case 'maxItems':
				case 'minProperties':
				case 'maxProperties':
				case 'minContains':
				case 'maxContains':
					if ( is_numeric( $value ) && (int) $value >= 0 && (float) $value === (float) (int) $value ) {
						$out[ $key ] = (int) $value;
					}
					break;
				case 'uniqueItems':
					if ( is_bool( $value ) ) {
						$out[ $key ] = $value;
					}
					break;
				case 'pattern':
					if ( is_string( $value ) && self::valid_pattern( $value ) ) {
						$out[ $key ] = $value;
					}
					break;
				case 'title':
				case 'description':
				case 'format':
					if ( is_string( $value ) ) {
						$out[ $key ] = $value;
					}
					break;
				default:
					$out[ $key ] = $value;
			}
		}
		// Con los draft-04 resueltos, el número de al lado de un exclusivo ya no hace falta.
		foreach ( array( 'exclusiveMinimum' => 'minimum', 'exclusiveMaximum' => 'maximum' ) as $exclusive => $limit ) {
			if ( true === ( $schema[ $exclusive ] ?? null ) && isset( $out[ $exclusive ], $out[ $limit ] ) ) {
				unset( $out[ $limit ] );
			}
		}
		if ( isset( $out['required'] ) ) {
			$names    = isset( $out['properties'] ) && is_array( $out['properties'] ) ? array_keys( $out['properties'] ) : null;
			$required = array();
			foreach ( $out['required'] as $name ) {
				if ( is_string( $name ) && ( null === $names || in_array( $name, $names, true ) ) && ! in_array( $name, $required, true ) ) {
					$required[] = $name;
				}
			}
			if ( $required ) {
				$out['required'] = $required;
			} else {
				unset( $out['required'] );
			}
		}
		$type = $out['type'] ?? null;
		if ( array_key_exists( 'default', $out ) && array() === $out['default'] && ( 'object' === $type || ( is_array( $type ) && in_array( 'object', $type, true ) ) || isset( $out['properties'] ) ) ) {
			$out['default'] = new stdClass();
		}
		// OpenAI exige items en todo arreglo.
		if ( ( 'array' === $type || ( is_array( $type ) && in_array( 'array', $type, true ) ) ) && ! isset( $out['items'] ) ) {
			$out['items'] = new stdClass();
		}
		return $out ? $out : new stdClass();
	}

	/** ¿Compila como expresión regular? (las de JSON Schema son de ECMAScript; las que PCRE no entiende se quitan). */
	private static function valid_pattern( $pattern ) {
		return '' !== $pattern && false !== @preg_match( '/' . str_replace( '/', '\/', $pattern ) . '/u', '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	private static function is_list( array $value ) {
		return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Esquema en el subconjunto que acepta Gemini (tipo, descripción, enum, propiedades, requeridos, elementos y
	 * límites). Un objeto libre (sin propiedades) se pide como texto JSON, que Tools::coerce() decodifica.
	 *
	 * @return array|null null para la raíz sin propiedades (la función se declara sin parámetros).
	 */
	public static function google_schema( $schema, $root = false ) {
		$schema = is_array( $schema ) ? $schema : array();
		foreach ( array( 'anyOf', 'oneOf' ) as $union ) {
			if ( empty( $schema['type'] ) && ! empty( $schema[ $union ][0] ) && is_array( $schema[ $union ][0] ) ) {
				$schema = array_merge( $schema[ $union ][0], array_intersect_key( $schema, array( 'description' => 1 ) ) );
			}
		}
		$out  = array();
		$type = $schema['type'] ?? null;
		if ( is_array( $type ) ) {
			if ( in_array( 'null', $type, true ) ) {
				$out['nullable'] = true;
			}
			$type = current( array_diff( $type, array( 'null' ) ) );
		}
		if ( ! is_string( $type ) || '' === $type ) {
			$type = isset( $schema['properties'] ) ? 'object' : ( isset( $schema['items'] ) ? 'array' : 'string' );
		}
		$description = isset( $schema['description'] ) && is_string( $schema['description'] ) ? $schema['description'] : '';
		if ( 'object' === $type ) {
			$props = array();
			foreach ( ( is_array( $schema['properties'] ?? null ) ? $schema['properties'] : array() ) as $name => $sub ) {
				$props[ (string) $name ] = self::google_schema( $sub );
			}
			if ( ! $props ) {
				if ( $root ) {
					return null;
				}
				return array( 'type' => 'STRING', 'description' => trim( $description . ' (objeto JSON escrito como texto)' ) );
			}
			$out['type']       = 'OBJECT';
			$out['properties'] = $props;
			$required          = array_values( array_intersect( array_map( 'strval', (array) ( $schema['required'] ?? array() ) ), array_keys( $props ) ) );
			if ( $required ) {
				$out['required'] = $required;
			}
		} elseif ( 'array' === $type ) {
			$out['type']  = 'ARRAY';
			$out['items'] = self::google_schema( is_array( $schema['items'] ?? null ) ? $schema['items'] : array( 'type' => 'string' ) );
			foreach ( array( 'minItems', 'maxItems' ) as $limit ) {
				if ( isset( $schema[ $limit ] ) && is_numeric( $schema[ $limit ] ) ) {
					$out[ $limit ] = (int) $schema[ $limit ];
				}
			}
		} else {
			$map         = array( 'integer' => 'INTEGER', 'number' => 'NUMBER', 'boolean' => 'BOOLEAN' );
			$out['type'] = $map[ $type ] ?? 'STRING';
			if ( 'STRING' === $out['type'] && ! empty( $schema['enum'] ) && is_array( $schema['enum'] ) ) {
				$out['enum'] = array_values( array_map( 'strval', array_filter( $schema['enum'], 'is_scalar' ) ) );
			}
			foreach ( array( 'minimum', 'maximum' ) as $limit ) {
				if ( isset( $schema[ $limit ] ) && is_numeric( $schema[ $limit ] ) && 'STRING' !== $out['type'] ) {
					$out[ $limit ] = $schema[ $limit ] + 0;
				}
			}
		}
		if ( '' !== $description ) {
			$out['description'] = $description;
		}
		return $out;
	}
}

