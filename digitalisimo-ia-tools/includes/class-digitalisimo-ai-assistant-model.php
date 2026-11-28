<?php
defined( 'ABSPATH' ) || exit;

/**
 * El modelo del Asistente IA (docs/ASSISTANT.md): la IA y la clave que ya configura IA Tools, con llamadas a
 * herramientas en el formato de cada proveedor (OpenAI y compatibles —DeepSeek, xAI—, Anthropic y Google).
 *
 * - El modelo, el proveedor y el esfuerzo del Asistente los decide DIGITALÍSIMO, que paga la API (pedido del dueño del
 *   2026-11-27: «no permitas que el usuario cambie el modelo todavía porque ese lo pagaré yo, debemos darle el
 *   asistente ya optimizado»). Salen de fixed(): PROVIDER, MODEL y EFFORT de esta clase; para DIGITALÍSIMO, las
 *   constantes de wp-config DIGITALISIMO_AI_ASSISTANT_MODEL, DIGITALISIMO_AI_ASSISTANT_PROVIDER y
 *   DIGITALISIMO_AI_ASSISTANT_EFFORT o el filtro `digitalisimo_ai_assistant_model`. Nada de la pantalla, la REST ni el
 *   MCP los cambia: no hay selector, la IA que pida el navegador se ignora, y lo que quedara guardado de antes (la
 *   opción `assistant_models`, un perfil de uso llamado «assistant» o el de contenido) ya no cuenta. La clave sigue
 *   siendo la del proveedor en IA Tools → Proveedores (del sitio o heredada de la red).
 * - La clave sólo viaja del servidor al proveedor: nada de esta clase la devuelve al navegador. De un error sólo se
 *   muestra el mensaje del proveedor (`error.message`), recortado y sin claves; con 401/403, ni eso.
 * - Si el proveedor rechaza la petición (400) por una herramienta, se quita esa herramienta y se repite (MAX_DROPS).
 * - Los mensajes llegan en un formato neutro (user, assistant con calls, tool con call y content) y aquí se traducen.
 * - `digitalisimo_ai_assistant_pre_model` permite sustituir la llamada (pruebas y proveedores simulados).
 */
final class Digitalisimo_AI_Assistant_Model {
	const NAMES         = array( 'openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'google' => 'Google', 'xai' => 'xAI', 'deepseek' => 'DeepSeek' );
	const AI_NAMES      = array( 'openai' => 'ChatGPT', 'anthropic' => 'Claude', 'google' => 'Gemini', 'xai' => 'Grok', 'deepseek' => 'DeepSeek' );
	const OPENAI_URLS   = array( 'openai' => 'https://api.openai.com/v1/chat/completions', 'deepseek' => 'https://api.deepseek.com/v1/chat/completions', 'xai' => 'https://api.x.ai/v1/chat/completions' );
	/**
	 * El modelo de fábrica del Asistente: el más barato que pasó 9 de 9 el ensayo real del caso plomerito con el contexto
	 * recortado (docs/ASSISTANT.md, «Modelo fijo: medición»; 2026-11-27: Claude Haiku 5.5 a unos 0,015 US$ por corrida,
	 * Sonnet 5.5 a 0,18 y Opus 5.5 a 0,38). Lo decide DIGITALÍSIMO; quien usa el sitio no lo cambia.
	 */
	const PROVIDER = 'anthropic';
	const MODEL    = 'claude-haiku-5-5';
	/** Constantes de wp-config con las que DIGITALÍSIMO fija otro modelo, proveedor o esfuerzo en un sitio. */
	const MODEL_CONSTANT    = 'DIGITALISIMO_AI_ASSISTANT_MODEL';
	const PROVIDER_CONSTANT = 'DIGITALISIMO_AI_ASSISTANT_PROVIDER';
	const EFFORT_CONSTANT   = 'DIGITALISIMO_AI_ASSISTANT_EFFORT';
	const EFFORTS           = array( 'low', 'medium', 'high', 'xhigh', 'max' );
	/**
	 * La clave de Anthropic de DIGITALÍSIMO en wp-config (opcional). Con ella el Asistente no usa la de IA Tools →
	 * Proveedores, la clave no se guarda en la base ni sale nunca del servidor, y los demás perfiles no la usan.
	 */
	const KEY_CONSTANT      = 'DIGITALISIMO_AI_ASSISTANT_ANTHROPIC_KEY';
	/** Lo que dice el Asistente cuando el sitio no tiene la IA de DIGITALÍSIMO (también la pantalla, para quien administra). */
	const MISSING           = 'El Asistente IA necesita la IA de DIGITALÍSIMO y este sitio todavía no la tiene conectada. Pídele a DIGITALÍSIMO que la conecte.';
	/** Topes del Asistente con la clave de DIGITALÍSIMO (constantes opcionales de wp-config; nunca 0 = sin tope). */
	const DAILY_CONSTANT       = 'DIGITALISIMO_AI_ASSISTANT_DAILY_CALLS';
	const CONCURRENCY_CONSTANT = 'DIGITALISIMO_AI_ASSISTANT_CONCURRENCY';
	const OWN_DAILY            = 200;
	const OWN_CONCURRENCY      = 1;
	/**
	 * Modelos de Anthropic que escriben sus notas entre llamadas a herramientas como bloques `thinking` (vacíos salvo
	 * con `display: "updates"`, beta UPDATES_BETA): sin pedirlas, un turno que termina en una herramienta llega sin texto.
	 */
	const UPDATES_MODELS = '/^claude-(opus-5-5|sonnet-5-5|haiku-5-5|fable-5|mythos-5)/';
	const UPDATES_BETA   = 'thinking-display-updates-2026-08-18';
	/**
	 * Los bloques thinking del turno en curso se reenvían tal cual (lo exige la API). Si el principio de la conversación
	 * cambió (p. ej. se compactó un resultado viejo), con `prefix_mismatch_behavior: drop_block` (beta BINDING_BETA) la
	 * API los descarta en vez de rechazar la petición.
	 */
	const BINDING_BETA   = 'thinking-binding-controls-2026-08-01';
	/** Esfuerzo de fábrica del Asistente con los modelos que lo admiten (Opus 5.5 y Haiku 5.5 traen `medium` por defecto). */
	const EFFORT         = 'high';
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
	 * El modelo fijo del Asistente: `provider`, `model` y `effort`. MODEL, PROVIDER y EFFORT de esta clase; encima, las
	 * constantes de wp-config (MODEL_CONSTANT, PROVIDER_CONSTANT y EFFORT_CONSTANT) y, al final, el filtro
	 * `digitalisimo_ai_assistant_model` (código de DIGITALÍSIMO, nunca una opción que se guarde desde el escritorio). Sin
	 * proveedor explícito, el del nombre del modelo (claude-… → Anthropic). Lo que no es válido se ignora.
	 *
	 * @return array{provider:string,model:string,effort:string}
	 */
	public static function fixed() {
		$fixed = array( 'provider' => '', 'model' => self::MODEL, 'effort' => self::EFFORT );
		if ( defined( self::MODEL_CONSTANT ) && self::valid_model( constant( self::MODEL_CONSTANT ) ) ) {
			$fixed['model'] = trim( (string) constant( self::MODEL_CONSTANT ) );
		}
		if ( defined( self::PROVIDER_CONSTANT ) && is_string( constant( self::PROVIDER_CONSTANT ) ) && isset( self::NAMES[ strtolower( trim( constant( self::PROVIDER_CONSTANT ) ) ) ] ) ) {
			$fixed['provider'] = strtolower( trim( constant( self::PROVIDER_CONSTANT ) ) );
		}
		if ( defined( self::EFFORT_CONSTANT ) && in_array( constant( self::EFFORT_CONSTANT ), self::EFFORTS, true ) ) {
			$fixed['effort'] = constant( self::EFFORT_CONSTANT );
		}
		$filtered = apply_filters( 'digitalisimo_ai_assistant_model', $fixed );
		if ( is_array( $filtered ) ) {
			if ( self::valid_model( $filtered['model'] ?? null ) ) {
				$fixed['model'] = trim( (string) $filtered['model'] );
			}
			if ( is_string( $filtered['provider'] ?? null ) && ( '' === $filtered['provider'] || isset( self::NAMES[ $filtered['provider'] ] ) ) ) {
				$fixed['provider'] = $filtered['provider'];
			}
			if ( in_array( $filtered['effort'] ?? null, self::EFFORTS, true ) ) {
				$fixed['effort'] = $filtered['effort'];
			}
		}
		if ( '' === $fixed['provider'] ) {
			$fixed['provider'] = self::provider_of( $fixed['model'] );
		}
		// Proveedor y modelo cruzados (p. ej. PROVIDER en openai con un modelo claude-…): se vuelve al de fábrica y queda un
		// aviso en la bitácora del Asistente (una vez al día).
		$known = self::known_provider( $fixed['model'] );
		if ( '' !== $known && $known !== $fixed['provider'] ) {
			self::warn_mismatch( $fixed );
			$fixed = array( 'provider' => self::PROVIDER, 'model' => self::MODEL, 'effort' => $fixed['effort'] );
		}
		return $fixed;
	}

	/** El aviso de proveedor y modelo cruzados, en la bitácora del Asistente y en el registro de PHP (una vez al día). */
	private static function warn_mismatch( array $fixed ) {
		$key  = 'digitalisimo_ai_assistant_model_mismatch';
		$seen = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
		if ( $seen ) {
			return;
		}
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, 1, DAY_IN_SECONDS );
		}
		$detail = sprintf( 'El modelo fijo del Asistente no cuadra con su proveedor (%s con %s): se usa el de fábrica, %s.', $fixed['model'], $fixed['provider'], self::MODEL );
		if ( class_exists( 'Digitalisimo_AI_Assistant_Tools' ) ) {
			Digitalisimo_AI_Assistant_Tools::log( array( 'user' => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0, 'ability' => 'modelo del Asistente', 'kind' => 'rejected', 'input' => '', 'result' => 'skipped', 'detail' => $detail ) );
		}
		error_log( 'DIGITALÍSIMO IA Tools: ' . $detail ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/** El proveedor que se reconoce por el nombre del modelo, o '' si el nombre no es de ninguno conocido. */
	public static function known_provider( $model ) {
		$model = strtolower( (string) $model );
		foreach ( array( '/^claude-/' => 'anthropic', '/^(gpt-|o\d|chatgpt)/' => 'openai', '/^(models\/)?gemini/' => 'google', '/^grok/' => 'xai', '/^deepseek/' => 'deepseek' ) as $pattern => $provider ) {
			if ( preg_match( $pattern, $model ) ) {
				return $provider;
			}
		}
		return '';
	}

	/**
	 * Con la clave de DIGITALÍSIMO, los topes del Asistente por persona salen de wp-config y no del escritorio: llamadas
	 * por día (DAILY_CONSTANT, OWN_DAILY por defecto) y peticiones a la vez (CONCURRENCY_CONSTANT, OWN_CONCURRENCY, de 1
	 * a 10). Nunca 0: no hay forma de quitar el tope. null sin la clave (valen los de IA Tools).
	 *
	 * @return array{per_day:int,concurrent:int}|null
	 */
	public static function own_limits() {
		if ( '' === self::own_key() ) {
			return null;
		}
		$daily = defined( self::DAILY_CONSTANT ) && is_numeric( constant( self::DAILY_CONSTANT ) ) ? (int) constant( self::DAILY_CONSTANT ) : self::OWN_DAILY;
		$conc  = defined( self::CONCURRENCY_CONSTANT ) && is_numeric( constant( self::CONCURRENCY_CONSTANT ) ) ? (int) constant( self::CONCURRENCY_CONSTANT ) : self::OWN_CONCURRENCY;
		return array( 'per_day' => $daily >= 1 ? $daily : self::OWN_DAILY, 'concurrent' => max( 1, min( 10, $conc ) ) );
	}

	/** La clave de KEY_CONSTANT si está en wp-config ('' si no). Sólo para el servidor. */
	public static function own_key() {
		$key = defined( self::KEY_CONSTANT ) ? constant( self::KEY_CONSTANT ) : '';
		return is_string( $key ) && strlen( trim( $key ) ) >= 8 ? trim( $key ) : '';
	}

	/** ¿Es un ID de modelo que se puede mandar? (letras, números y . _ : / -; hasta 100). */
	private static function valid_model( $model ) {
		return is_string( $model ) && (bool) preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,99}$/', trim( $model ) );
	}

	/** El proveedor de un modelo por su nombre (claude-… Anthropic, gpt-… OpenAI…); si no se reconoce, PROVIDER. */
	public static function provider_of( $model ) {
		$known = self::known_provider( $model );
		return '' !== $known ? $known : self::PROVIDER;
	}

	/**
	 * La IA del Asistente si su proveedor está activo con su clave: `provider` => (`provider`, `model`, `name`,
	 * `ai_name`, `profile`), o un arreglo vacío. Es una sola, la de fixed(): ni los perfiles de uso ni las opciones
	 * guardadas la cambian. Sin claves.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function targets() {
		$fixed     = self::fixed();
		$id        = $fixed['provider'];
		$providers = (array) Digitalisimo_AI::get( 'providers' );
		$own       = 'anthropic' === $id && '' !== self::own_key();
		if ( ! isset( self::NAMES[ $id ] ) || ( ! $own && ( empty( $providers[ $id ]['enabled'] ) || empty( $providers[ $id ]['key'] ) ) ) ) {
			return array();
		}
		return array( $id => array( 'provider' => $id, 'model' => $fixed['model'], 'name' => self::NAMES[ $id ], 'ai_name' => self::AI_NAMES[ $id ], 'profile' => '' ) );
	}

	/**
	 * La IA para una conversación, con su clave, su URL y su esfuerzo: sólo para el servidor. Siempre la fija
	 * (fixed()): `$choice` se conserva por compatibilidad y se ignora (antes era «auto» o un proveedor que pedía el
	 * navegador; ya no se elige).
	 */
	public static function target( $choice = 'auto' ) {
		unset( $choice );
		$targets = self::targets();
		if ( ! $targets ) {
			return new WP_Error( 'digitalisimo_assistant_no_ai', self::MISSING, array( 'status' => 409 ) );
		}
		$target                = reset( $targets );
		$providers             = (array) Digitalisimo_AI::get( 'providers' );
		$own                   = 'anthropic' === $target['provider'] ? self::own_key() : '';
		$target['key']         = '' !== $own ? $own : (string) ( $providers[ $target['provider'] ]['key'] ?? '' );
		// Con Anthropic, siempre su URL oficial (build() la pone con `url` vacío): una URL que alguien guarde en
		// Proveedores no recibe la clave que paga DIGITALÍSIMO.
		$target['url']         = 'anthropic' === $target['provider'] ? '' : (string) ( $providers[ $target['provider'] ]['url'] ?? '' );
		$target['turn_effort'] = self::fixed()['effort'];
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
		$pre = apply_filters( 'digitalisimo_ai_assistant_pre_model', null, array( 'provider' => $target['provider'], 'model' => $target['model'], 'system' => self::system_text( $system ), 'system_parts' => is_array( $system ) ? $system : array( 'fixed' => (string) $system ), 'messages' => $messages, 'tools' => $tools ) );
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
			// Sin redirecciones (como Hosting con Webuzo): una respuesta 30x no se sigue, así que la clave no viaja a otro lado.
			$response = wp_remote_post( $request['url'], array( 'timeout' => $timeout, 'redirection' => 0, 'headers' => $request['headers'], 'body' => wp_json_encode( $request['body'] ) ) );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( 400 !== $code ) {
				break;
			}
			$raw    = (string) wp_remote_retrieve_body( $response );
			$reason = self::error_reason( $raw );
			$index  = self::rejected_tool( $target['provider'], $reason . ' ' . $raw );
			// Si la API no acepta las notas de avance (`display: "updates"` o su beta), se repite una vez sin ellas.
			// Si la API no acepta lo propio de los modelos 5.x (notas de avance, esfuerzo, vínculo de los bloques thinking),
			// se repite una vez sin eso; si aún rechaza un bloque thinking reenviado, una vez más sin reenviarlos.
			if ( null === $index && 'anthropic' === $target['provider'] && preg_match( '/thinking|display|beta|block_binding|output_config|effort|signature/i', $raw ) ) {
				if ( empty( $target['no_updates'] ) && ( ! empty( $request['body']['thinking'] ) || ! empty( $request['body']['output_config'] ) ) ) {
					$target['no_updates'] = true;
					continue;
				}
				if ( empty( $target['no_replay'] ) && false !== strpos( (string) wp_json_encode( $request['body']['messages'] ?? array() ), '"thinking"' ) ) {
					$target['no_replay'] = true;
					continue;
				}
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
				// Con la clave de DIGITALÍSIMO (wp-config) quien administra no la puede revisar: que avise a DIGITALÍSIMO.
				$why = 'HTTP ' . $code . ': ' . ( 'anthropic' === $target['provider'] && '' !== self::own_key() ? 'avisa a DIGITALÍSIMO para que revise la conexión' : 'revisa la clave en IA Tools' );
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

	/** Las instrucciones como un solo texto (las partes de Digitalisimo_AI_Assistant::system_parts(), en su orden). */
	public static function system_text( $system ) {
		return is_array( $system ) ? implode( "\n\n", array_filter( array_map( 'strval', $system ), 'strlen' ) ) : (string) $system;
	}

	/**
	 * Las instrucciones para Anthropic con sus puntos de caché (docs/ASSISTANT.md, «Caché»): el prefijo se ordena de lo
	 * más estable a lo que más cambia —herramientas (en orden fijo, Tools::definitions()), la parte fija, el aprendizaje
	 * del sitio y, al final, la parte variable (persona y fecha)— y lleva un punto de caché al final de la parte fija y
	 * otro al final del aprendizaje. Con la caché automática de la conversación (`cache_control` del cuerpo) son tres de
	 * los cuatro que admite la API. Sin caché (`$cache` false), un texto.
	 *
	 * @return string|array
	 */
	public static function anthropic_system( $system, $cache ) {
		if ( ! is_array( $system ) ) {
			return (string) $system;
		}
		if ( ! $cache ) {
			return self::system_text( $system );
		}
		$blocks = array();
		foreach ( array( 'fixed', 'learning', 'variable' ) as $part ) {
			$text = (string) ( $system[ $part ] ?? '' );
			if ( '' === $text ) {
				continue;
			}
			$block = array( 'type' => 'text', 'text' => $text );
			if ( 'variable' !== $part ) {
				$block['cache_control'] = array( 'type' => 'ephemeral' );
			}
			$blocks[] = $block;
		}
		return $blocks;
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
			$body = array( 'model' => $target['model'], 'messages' => self::openai_messages( self::system_text( $system ), $messages ) );
			if ( $tools ) {
				$body['tools'] = array();
				foreach ( $tools as $tool ) {
					$body['tools'][] = array( 'type' => 'function', 'function' => array( 'name' => $tool['name'], 'description' => $tool['description'], 'parameters' => self::object_schema( $tool['schema'] ) ) );
				}
			}
			return array( 'url' => $url ?: self::OPENAI_URLS[ $id ], 'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key ), 'body' => $body, 'tool_names' => $names );
		}
		if ( 'anthropic' === $id ) {
			// Caché de la API de Anthropic: cada paso de un turno reenvía la conversación entera (la memoria completa) y
			// sólo crece al final. La caché automática (`cache_control` del cuerpo) pone el punto en el último bloque, así
			// que cada paso y cada turno leen lo ya enviado; las instrucciones llevan sus propios puntos (anthropic_system()).
			// No con una URL compatible propia (puede no admitirlo).
			$cache = '' === $url && empty( $target['no_cache'] ) && (bool) apply_filters( 'digitalisimo_ai_assistant_prompt_cache', true, $target );
			$body  = array( 'model' => $target['model'], 'max_tokens' => (int) ( $target['max_tokens'] ?? self::MAX_TOKENS ), 'system' => self::anthropic_system( $system, $cache ), 'messages' => self::anthropic_messages( $messages, empty( $target['no_replay'] ) ) );
			if ( $cache ) {
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
				$body['thinking']          = array( 'type' => 'adaptive', 'display' => 'updates', 'block_binding' => array( 'prefix_mismatch_behavior' => 'drop_block' ) );
				$body['output_config']     = array( 'effort' => (string) apply_filters( 'digitalisimo_ai_assistant_effort', (string) ( $target['turn_effort'] ?? self::EFFORT ), $target ) );
				$headers['anthropic-beta'] = self::UPDATES_BETA . ',' . self::BINDING_BETA;
			} elseif ( ! empty( $target['effort'] ) && preg_match( self::UPDATES_MODELS, (string) $target['model'] ) ) {
				// Tareas cortas sin herramientas (el aprendizaje): esfuerzo propio, sin notas de avance.
				$body['output_config'] = array( 'effort' => (string) $target['effort'] );
			}
			return array( 'url' => $url ?: 'https://api.anthropic.com/v1/messages', 'headers' => $headers, 'body' => $body, 'tool_names' => $names );
		}
		if ( 'google' === $id ) {
			$body = array( 'systemInstruction' => array( 'parts' => array( array( 'text' => self::system_text( $system ) ) ) ), 'contents' => self::google_messages( $messages ) );
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
		$notes     = '';
		$thinking  = array();
		$usage     = self::usage( $provider, $data );
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
				} elseif ( in_array( $block['type'] ?? '', array( 'thinking', 'redacted_thinking' ), true ) ) {
					// Se guarda tal cual para reenviarlo en los pasos siguientes del turno; con `display: "updates"`, el texto
					// de un bloque thinking es una nota de avance que la persona ve (nunca vuelve al modelo como texto).
					$thinking[] = $block;
					$note       = trim( (string) ( $block['thinking'] ?? '' ) );
					if ( 'thinking' === $block['type'] && '' !== $note && self::UPDATES_CUT !== $note ) {
						$notes .= ( '' !== $notes ? "\n\n" : '' ) . $note;
					}
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
		return self::normalize( array( 'text' => $text, 'calls' => $calls, 'refusal' => $refusal, 'truncated' => $truncated, 'notes' => $notes, 'thinking' => $thinking, 'usage' => $usage ) );
	}

	/**
	 * Los tokens de una respuesta, iguales para los tres formatos: `input` (los que se cobraron completos), `cache_read`
	 * (leídos de la caché), `cache_write` (escritos en la caché) y `output`. Anthropic los separa; OpenAI y compatibles
	 * cuentan lo leído de la caché DENTRO de `prompt_tokens`, y Google dentro de `promptTokenCount`: aquí se restan.
	 */
	public static function usage( $provider, array $data ) {
		$n = function ( $value ) {
			return max( 0, (int) $value );
		};
		if ( 'anthropic' === $provider ) {
			$u = (array) ( $data['usage'] ?? array() );
			return array( 'input' => $n( $u['input_tokens'] ?? 0 ), 'cache_read' => $n( $u['cache_read_input_tokens'] ?? 0 ), 'cache_write' => $n( $u['cache_creation_input_tokens'] ?? 0 ), 'output' => $n( $u['output_tokens'] ?? 0 ) );
		}
		if ( 'google' === $provider ) {
			$u    = (array) ( $data['usageMetadata'] ?? array() );
			$read = $n( $u['cachedContentTokenCount'] ?? 0 );
			return array( 'input' => max( 0, $n( $u['promptTokenCount'] ?? 0 ) - $read ), 'cache_read' => $read, 'cache_write' => 0, 'output' => $n( $u['candidatesTokenCount'] ?? 0 ) + $n( $u['thoughtsTokenCount'] ?? 0 ) );
		}
		$u    = (array) ( $data['usage'] ?? array() );
		$read = $n( $u['prompt_tokens_details']['cached_tokens'] ?? ( $u['prompt_cache_hit_tokens'] ?? 0 ) );
		return array( 'input' => max( 0, $n( $u['prompt_tokens'] ?? 0 ) - $read ), 'cache_read' => $read, 'cache_write' => 0, 'output' => $n( $u['completion_tokens'] ?? 0 ) );
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
		$thinking = array();
		foreach ( (array) ( $reply['thinking'] ?? array() ) as $block ) {
			if ( is_array( $block ) && in_array( $block['type'] ?? '', array( 'thinking', 'redacted_thinking' ), true ) ) {
				$thinking[] = $block;
			}
		}
		$usage = array();
		foreach ( array( 'input', 'cache_read', 'cache_write', 'output' ) as $key ) {
			$usage[ $key ] = max( 0, (int) ( is_array( $reply['usage'] ?? null ) ? ( $reply['usage'][ $key ] ?? 0 ) : 0 ) );
		}
		return array( 'text' => is_string( $reply['text'] ?? null ) ? $reply['text'] : '', 'calls' => $calls, 'refusal' => is_string( $reply['refusal'] ?? null ) ? $reply['refusal'] : '', 'truncated' => ! empty( $reply['truncated'] ), 'notes' => is_string( $reply['notes'] ?? null ) ? $reply['notes'] : '', 'thinking' => $thinking, 'usage' => $usage );
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
	private static function anthropic_messages( array $messages, $replay = true ) {
		$out = array();
		foreach ( $messages as $m ) {
			$blocks = array();
			if ( 'user' === $m['role'] ) {
				$role     = 'user';
				$blocks[] = array( 'type' => 'text', 'text' => (string) $m['text'] );
			} elseif ( 'assistant' === $m['role'] ) {
				$role = 'assistant';
				// Los bloques thinking del turno en curso (for_model sólo los deja ahí) van primero y sin cambios.
				foreach ( $replay ? (array) ( $m['thinking'] ?? array() ) : array() as $block ) {
					$blocks[] = $block;
				}
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

