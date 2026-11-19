<?php
defined( 'ABSPATH' ) || exit;

/**
 * El modelo del Asistente IA (docs/ASSISTANT.md): la IA y la clave que ya configura IA Tools, con llamadas a
 * herramientas en el formato de cada proveedor (OpenAI y compatibles —DeepSeek, xAI—, Anthropic y Google).
 *
 * - Las IA que se ofrecen salen de los perfiles de uso (menos «images») cuyo proveedor está activo y tiene clave; el
 *   modelo es el del perfil, nunca uno que elija la persona. «Auto» es el primer perfil disponible en
 *   PROFILE_ORDER (el de contenido, salvo que exista uno llamado «assistant»).
 * - La clave sólo viaja del servidor al proveedor: nada de esta clase la devuelve al navegador, y un error nunca
 *   repite el cuerpo de la respuesta del proveedor.
 * - Los mensajes llegan en un formato neutro (user, assistant con calls, tool con call y content) y aquí se traducen.
 * - `digitalisimo_ai_assistant_pre_model` permite sustituir la llamada (pruebas y proveedores simulados).
 */
final class Digitalisimo_AI_Assistant_Model {
	const PROFILE_ORDER = array( 'assistant', 'content', 'chatbot', 'seo', 'ecommerce', 'automation' );
	const NAMES         = array( 'openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'google' => 'Google', 'xai' => 'xAI', 'deepseek' => 'DeepSeek' );
	const AI_NAMES      = array( 'openai' => 'ChatGPT', 'anthropic' => 'Claude', 'google' => 'Gemini', 'xai' => 'Grok', 'deepseek' => 'DeepSeek' );
	const OPENAI_URLS   = array( 'openai' => 'https://api.openai.com/v1/chat/completions', 'deepseek' => 'https://api.deepseek.com/v1/chat/completions', 'xai' => 'https://api.x.ai/v1/chat/completions' );
	const MAX_TOKENS    = 2048;
	const TIMEOUT       = 60;

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
			$out[ $id ] = array( 'provider' => $id, 'model' => $model, 'name' => self::NAMES[ $id ], 'ai_name' => self::AI_NAMES[ $id ], 'profile' => (string) $profile );
		}
		return $out;
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
		$request = self::build( $target, $system, $messages, $tools );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$response = wp_remote_post( $request['url'], array( 'timeout' => self::TIMEOUT, 'headers' => $request['headers'], 'body' => wp_json_encode( $request['body'] ) ) );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || $code < 200 || $code >= 300 ) {
			Digitalisimo_AI::log( 'assistant', $target['provider'], false, $code );
			$ai = self::AI_NAMES[ $target['provider'] ] ?? $target['provider'];
			$why = 0 === $code ? 'sin conexión con el proveedor' : ( in_array( $code, array( 401, 403 ), true ) ? 'HTTP ' . $code . ': revisa la clave en IA Tools' : 'HTTP ' . $code );
			return new WP_Error( 'digitalisimo_assistant_provider', sprintf( '%s no respondió (%s).', $ai, $why ), array( 'status' => 502 ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		Digitalisimo_AI::log( 'assistant', $target['provider'], true, $code );
		return self::parse( $target['provider'], is_array( $data ) ? $data : array() );
	}

	/** URL, cabeceras y cuerpo de la petición al proveedor (pura: la prueba la lee sin red). */
	public static function build( array $target, $system, array $messages, array $tools ) {
		$id  = $target['provider'];
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
			return array( 'url' => $url ?: self::OPENAI_URLS[ $id ], 'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key ), 'body' => $body );
		}
		if ( 'anthropic' === $id ) {
			$body = array( 'model' => $target['model'], 'max_tokens' => self::MAX_TOKENS, 'system' => $system, 'messages' => self::anthropic_messages( $messages ) );
			if ( $tools ) {
				$body['tools'] = array();
				foreach ( $tools as $tool ) {
					$body['tools'][] = array( 'name' => $tool['name'], 'description' => $tool['description'], 'input_schema' => self::object_schema( $tool['schema'] ) );
				}
			}
			return array( 'url' => $url ?: 'https://api.anthropic.com/v1/messages', 'headers' => array( 'Content-Type' => 'application/json', 'x-api-key' => $key, 'anthropic-version' => '2023-06-01' ), 'body' => $body );
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
			return array( 'url' => $url ?: 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $target['model'] ) . ':generateContent', 'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => $key ), 'body' => $body );
		}
		return new WP_Error( 'digitalisimo_assistant_ai', 'Proveedor no compatible.', array( 'status' => 400 ) );
	}

	/** Respuesta del proveedor al formato neutro. */
	public static function parse( $provider, array $data ) {
		$text  = '';
		$calls = array();
		if ( isset( self::OPENAI_URLS[ $provider ] ) ) {
			$message = (array) ( $data['choices'][0]['message'] ?? array() );
			$text    = is_string( $message['content'] ?? null ) ? $message['content'] : '';
			foreach ( (array) ( $message['tool_calls'] ?? array() ) as $call ) {
				$input   = json_decode( (string) ( $call['function']['arguments'] ?? '' ), true );
				$calls[] = array( 'id' => (string) ( $call['id'] ?? '' ), 'name' => (string) ( $call['function']['name'] ?? '' ), 'input' => is_array( $input ) ? $input : array() );
			}
		} elseif ( 'anthropic' === $provider ) {
			foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
				if ( 'text' === ( $block['type'] ?? '' ) ) {
					$text .= (string) ( $block['text'] ?? '' );
				} elseif ( 'tool_use' === ( $block['type'] ?? '' ) ) {
					$calls[] = array( 'id' => (string) ( $block['id'] ?? '' ), 'name' => (string) ( $block['name'] ?? '' ), 'input' => is_array( $block['input'] ?? null ) ? $block['input'] : array() );
				}
			}
		} elseif ( 'google' === $provider ) {
			foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
				if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
					$text .= (string) $part['text'];
				}
				if ( isset( $part['functionCall'] ) ) {
					$calls[] = array( 'id' => (string) ( $part['functionCall']['id'] ?? '' ), 'name' => (string) ( $part['functionCall']['name'] ?? '' ), 'input' => is_array( $part['functionCall']['args'] ?? null ) ? $part['functionCall']['args'] : array(), 'sig' => (string) ( $part['thoughtSignature'] ?? '' ) );
				}
			}
		}
		return self::normalize( array( 'text' => $text, 'calls' => $calls ) );
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
		return array( 'text' => is_string( $reply['text'] ?? null ) ? $reply['text'] : '', 'calls' => $calls );
	}

	private static function openai_messages( $system, array $messages ) {
		$out = array( array( 'role' => 'system', 'content' => (string) $system ) );
		foreach ( $messages as $m ) {
			if ( 'user' === $m['role'] ) {
				$out[] = array( 'role' => 'user', 'content' => (string) $m['text'] );
			} elseif ( 'assistant' === $m['role'] ) {
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

	/** Esquema de entrada como objeto JSON válido para los tres formatos: `properties` vacías como `{}`. */
	public static function object_schema( $schema ) {
		$schema = is_array( $schema ) ? $schema : array();
		if ( 'object' !== ( $schema['type'] ?? 'object' ) ) {
			$schema = array( 'type' => 'object', 'properties' => array( 'value' => $schema ), 'required' => array( 'value' ) );
		}
		$schema['type'] = 'object';
		if ( ! isset( $schema['properties'] ) ) {
			$schema['properties'] = array();
		}
		return self::fix_objects( $schema );
	}

	private static function fix_objects( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}
		foreach ( $schema as $key => $value ) {
			if ( 'properties' === $key ) {
				$props = array();
				foreach ( (array) $value as $name => $sub ) {
					$props[ $name ] = self::fix_objects( $sub );
				}
				$schema[ $key ] = $props ? $props : new stdClass();
			} elseif ( is_array( $value ) && in_array( $key, array( 'items', 'additionalProperties', 'anyOf', 'oneOf', 'allOf', 'not' ), true ) ) {
				$schema[ $key ] = self::is_list( $value ) ? array_map( array( __CLASS__, 'fix_objects' ), $value ) : self::fix_objects( $value );
			}
		}
		if ( array_key_exists( 'default', $schema ) && array() === $schema['default'] && 'object' === ( $schema['type'] ?? '' ) ) {
			$schema['default'] = new stdClass();
		}
		return $schema;
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

