<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registro de uso de IA (docs/IA_USAGE.md): cuánto gasta cada sitio en los proveedores de IA, por función, modelo,
 * usuario y día, con su costo estimado en USD (y MXN) y un presupuesto mensual opcional.
 *
 * Un solo punto de registro: la acción `http_api_debug` de WordPress, que corre después de CADA petición HTTP hecha con
 * wp_remote_*(). Se toma la petición si va a un proveedor (los dominios de OpenAI, Anthropic, Google, DeepSeek y xAI, o
 * la «URL compatible» de un proveedor de IA Tools) Y lleva una de las claves de IA Tools (comparada en el servidor,
 * nunca guardada). Así ninguna función se queda sin registrar, ni la que se agregue mañana: el chatbot, los asistentes
 * editoriales, el Asistente IA, las automatizaciones y SEO (por Digitalisimo_AI::complete()), las imágenes y la base de
 * conocimiento. De la respuesta sólo se lee el bloque de uso (`usage`, `usageMetadata`) y el modelo; de la petición,
 * el modelo. El texto, la clave y los datos del visitante nunca se guardan.
 *
 * La función que hizo la llamada sale de un contexto: begin()/end() alrededor de Digitalisimo_AI::complete() (con el
 * perfil: chatbot, content, seo…) y de la API del metabox editorial; el filtro `digitalisimo_ai_assistant_pre_model`
 * marca cada paso del Asistente IA; las rutas `/images/` y `/embeddings` son imágenes y base de conocimiento. Varias
 * peticiones dentro del mismo contexto (otro proveedor tras un error, la herramienta quitada que el Asistente repite)
 * se marcan como reintento.
 *
 * Con presupuesto y «pausar», `pre_http_request` corta la llamada antes de salir (sin costo) con un mensaje claro.
 */
final class Digitalisimo_AI_Usage {
	const SETTINGS   = 'digitalisimo_ai_usage_settings';
	const ALERTS     = 'digitalisimo_ai_usage_alerts';
	const CRON_PURGE = 'digitalisimo_ai_usage_purge';
	const CRON_ALERT = 'digitalisimo_ai_usage_alert';
	const ABILITY    = 'digitalisimo-ia/usage';
	const HOSTS      = array( 'api.openai.com', 'api.anthropic.com', 'generativelanguage.googleapis.com', 'api.deepseek.com', 'api.x.ai' );
	const FEATURES   = array(
		'chatbot'    => 'Chatbot público',
		'editorial'  => 'Asistente editorial',
		'content'    => 'Creación de contenido',
		'seo'        => 'SEO',
		'ecommerce'  => 'Ecommerce',
		'automation' => 'Automatizaciones',
		'assistant'  => 'Asistente IA',
		'learning'   => 'Aprendizaje del sitio',
		'images'     => 'Imágenes',
		'knowledge'  => 'Base de conocimiento',
		'other'      => 'Otra función',
	);
	/** Respuestas de más de 1 MB (imágenes en base64) no se decodifican enteras: sólo su bloque de uso. */
	const MAX_DECODE = 1048576;

	/** Contextos abiertos con begin(): `feature` y `attempts`. */
	private static $stack = array();
	/** Contexto del Asistente IA (lo pone cada paso; no tiene fin). */
	private static $ambient = null;
	/** Gasto del mes ya consultado en esta petición, por sitio y mes: [ 'sitio|mes' => USD ]. */
	private static $spend = array();
	/** Historial de Hosting del sitio principal, leído una vez por petición (mxn_info() en un subsitio). */
	private static $main_fx = array();

	public static function init() {
		add_action( 'http_api_debug', array( __CLASS__, 'capture' ), 10, 5 );
		add_filter( 'pre_http_request', array( __CLASS__, 'gate' ), 10, 3 );
		add_filter( 'digitalisimo_ai_assistant_pre_model', array( __CLASS__, 'assistant_step' ), 1, 2 );
		add_action( self::CRON_PURGE, array( __CLASS__, 'purge' ) );
		add_action( self::CRON_ALERT, array( __CLASS__, 'send_alerts' ) );
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_ability' ) );
		add_filter( 'elementor/mcp/server/tools', array( __CLASS__, 'server_tools' ) );
		if ( class_exists( 'Digitalisimo_AI_Usage_Admin' ) ) {
			Digitalisimo_AI_Usage_Admin::init();
		}
	}

	/* ---------------- Contexto: qué función llama ---------------- */

	/** Abre el contexto de una función (clave de FEATURES o un perfil de uso); devuelve la marca para end(). */
	public static function begin( $feature ) {
		$feature        = sanitize_key( (string) $feature );
		self::$stack[]  = array( 'feature' => '' !== $feature ? $feature : 'other', 'attempts' => 0 );
		return count( self::$stack );
	}

	public static function end( $mark ) {
		$mark = (int) $mark;
		if ( $mark > 0 && $mark <= count( self::$stack ) ) {
			array_splice( self::$stack, $mark - 1 );
		}
	}

	/** Cada paso del Asistente IA (antes de llamar al proveedor): su contexto y, con el sitio en pausa, el aviso. */
	public static function assistant_step( $pre, $payload = array() ) {
		if ( self::$stack ) {
			self::$stack[ count( self::$stack ) - 1 ]['attempts'] = 0;
		} else {
			self::$ambient = array( 'feature' => 'assistant', 'attempts' => 0 );
		}
		if ( null === $pre && self::paused() ) {
			return new WP_Error( 'digitalisimo_ai_budget_paused', self::pause_message(), array( 'status' => 429 ) );
		}
		return $pre;
	}

	/** Función de una llamada y si es un reintento (otra petición en el mismo contexto). */
	private static function context_for( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( self::$stack ) {
			$top = count( self::$stack ) - 1;
			self::$stack[ $top ]['attempts']++;
			$feature = self::$stack[0]['feature'];
			$retry   = self::$stack[ $top ]['attempts'] > 1;
		} elseif ( self::$ambient ) {
			self::$ambient['attempts']++;
			$feature = self::$ambient['feature'];
			$retry   = self::$ambient['attempts'] > 1;
		} else {
			$feature = 'other';
			$retry   = false;
		}
		// Las rutas propias de imágenes y de embeddings sólo las usan esas funciones, llame quien llame.
		if ( false !== strpos( $path, '/images/' ) ) {
			$feature = 'images';
		} elseif ( false !== strpos( $path, '/embeddings' ) ) {
			$feature = 'knowledge';
		}
		return array( $feature, $retry );
	}

	/* ---------------- Captura ---------------- */

	/**
	 * El proveedor de IA Tools de una petición, o '': el dominio es de un proveedor (o de su URL compatible) y la
	 * petición lleva una de las claves de IA Tools (Bearer, x-api-key, x-goog-api-key o ?key=).
	 */
	public static function provider_for( $url, $args ) {
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return '';
		}
		$providers = class_exists( 'Digitalisimo_AI' ) ? (array) Digitalisimo_AI::get( 'providers' ) : array();
		$hosts     = self::HOSTS;
		foreach ( $providers as $provider ) {
			$custom = is_array( $provider ) ? strtolower( (string) wp_parse_url( (string) ( $provider['url'] ?? '' ), PHP_URL_HOST ) ) : '';
			if ( '' !== $custom ) {
				$hosts[] = $custom;
			}
		}
		if ( ! in_array( $host, $hosts, true ) ) {
			return '';
		}
		$sent = self::credentials( $url, $args );
		if ( ! $sent ) {
			return '';
		}
		foreach ( $providers as $id => $provider ) {
			$key = is_array( $provider ) ? (string) ( $provider['key'] ?? '' ) : '';
			if ( '' === $key ) {
				continue;
			}
			foreach ( $sent as $candidate ) {
				if ( hash_equals( $key, $candidate ) ) {
					return sanitize_key( (string) $id );
				}
			}
		}
		return '';
	}

	/** Las credenciales que lleva una petición (sólo para compararlas; nunca se guardan). */
	private static function credentials( $url, $args ) {
		$out     = array();
		$headers = is_array( $args['headers'] ?? null ) ? $args['headers'] : array();
		foreach ( $headers as $name => $value ) {
			$name  = strtolower( (string) $name );
			$value = trim( (string) $value );
			if ( 'authorization' === $name && 0 === stripos( $value, 'bearer ' ) ) {
				$out[] = trim( substr( $value, 7 ) );
			} elseif ( in_array( $name, array( 'x-api-key', 'x-goog-api-key', 'api-key' ), true ) ) {
				$out[] = $value;
			}
		}
		$query = (string) wp_parse_url( (string) $url, PHP_URL_QUERY );
		if ( '' !== $query ) {
			parse_str( $query, $params );
			if ( isset( $params['key'] ) && is_string( $params['key'] ) ) {
				$out[] = $params['key'];
			}
		}
		return array_values( array_filter( $out, 'strlen' ) );
	}

	/** `http_api_debug`: registra la llamada si es de IA Tools. Nunca interrumpe la función que llamó. */
	public static function capture( $response, $context = 'response', $class = '', $args = array(), $url = '' ) {
		if ( 'response' !== $context ) {
			return;
		}
		try {
			$provider = self::provider_for( $url, is_array( $args ) ? $args : array() );
			if ( '' === $provider ) {
				return;
			}
			list( $feature, $retry ) = self::context_for( $url );
			$error  = is_wp_error( $response );
			$status = $error ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$body   = $error ? '' : (string) wp_remote_retrieve_body( $response );
			$parsed = self::parse( $provider, (string) $url, is_string( $args['body'] ?? null ) ? $args['body'] : '', $body );
			self::record(
				array(
					'feature'        => $feature,
					'provider'       => $provider,
					'model'          => $parsed['model'],
					'response_model' => $parsed['response_model'],
					'usage'          => $parsed['usage'],
					'status'         => $status,
					'is_error'       => $error || $status < 200 || $status >= 300,
					'is_retry'       => $retry,
				)
			);
		} catch ( Throwable $e ) {
			// El registro nunca rompe una función de IA.
			unset( $e );
		}
	}

	/**
	 * Modelo y uso de una llamada: `model` (el pedido), `response_model` (el que dice la respuesta) y `usage` con
	 * `input` (cobrados completos), `output`, `cache_read`, `cache_write` (total), `cache_write_1h` y `units` (imágenes).
	 */
	public static function parse( $provider, $url, $request_body, $response_body ) {
		$model = '';
		if ( preg_match( '#/models/([^/:?]+)#', $url, $m ) ) {
			$model = rawurldecode( $m[1] );
		} elseif ( preg_match( '/"model"\s*:\s*"([^"\\\\]{1,120})"/', (string) $request_body, $m ) ) {
			$model = $m[1];
		}
		$data = array();
		if ( '' !== $response_body ) {
			if ( strlen( $response_body ) <= self::MAX_DECODE ) {
				$decoded = json_decode( $response_body, true );
				$data    = is_array( $decoded ) ? $decoded : array();
			} else {
				$data = self::usage_slice( $response_body );
			}
		}
		$n     = function ( $value ) {
			return max( 0, (int) $value );
		};
		$usage = array( 'input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0, 'cache_write_1h' => 0, 'units' => 0 );
		if ( is_array( $data['usageMetadata'] ?? null ) ) {
			$u                   = $data['usageMetadata'];
			$usage['cache_read'] = $n( $u['cachedContentTokenCount'] ?? 0 );
			$usage['input']      = max( 0, $n( $u['promptTokenCount'] ?? 0 ) - $usage['cache_read'] );
			$usage['output']     = $n( $u['candidatesTokenCount'] ?? 0 ) + $n( $u['thoughtsTokenCount'] ?? 0 );
		} elseif ( is_array( $data['usage'] ?? null ) ) {
			$u = $data['usage'];
			if ( 'anthropic' === $provider || isset( $u['cache_read_input_tokens'] ) || isset( $u['cache_creation_input_tokens'] ) ) {
				// Anthropic separa lo leído y lo escrito en la caché de `input_tokens`.
				$usage['input']          = $n( $u['input_tokens'] ?? 0 );
				$usage['output']         = $n( $u['output_tokens'] ?? 0 );
				$usage['cache_read']     = $n( $u['cache_read_input_tokens'] ?? 0 );
				$usage['cache_write']    = $n( $u['cache_creation_input_tokens'] ?? 0 );
				$usage['cache_write_1h'] = min( $usage['cache_write'], $n( $u['cache_creation']['ephemeral_1h_input_tokens'] ?? 0 ) );
			} elseif ( isset( $u['prompt_tokens'] ) ) {
				// Chat Completions (OpenAI, DeepSeek, xAI) y embeddings: lo leído de la caché va DENTRO de prompt_tokens.
				$usage['cache_read'] = $n( $u['prompt_tokens_details']['cached_tokens'] ?? ( $u['prompt_cache_hit_tokens'] ?? 0 ) );
				$usage['input']      = max( 0, $n( $u['prompt_tokens'] ) - $usage['cache_read'] );
				$usage['output']     = $n( $u['completion_tokens'] ?? 0 );
			} else {
				// Responses e imágenes de OpenAI: input_tokens incluye lo leído de la caché.
				$usage['cache_read'] = $n( $u['input_tokens_details']['cached_tokens'] ?? 0 );
				$usage['input']      = max( 0, $n( $u['input_tokens'] ?? 0 ) - $usage['cache_read'] );
				$usage['output']     = $n( $u['output_tokens'] ?? 0 );
			}
		}
		if ( false !== strpos( (string) wp_parse_url( $url, PHP_URL_PATH ), '/images/' ) ) {
			$usage['units'] = is_array( $data['data'] ?? null ) ? count( $data['data'] ) : ( isset( $data['images'] ) ? (int) $data['images'] : 0 );
		}
		$response_model = is_string( $data['model'] ?? null ) ? $data['model'] : ( is_string( $data['modelVersion'] ?? null ) ? $data['modelVersion'] : '' );
		return array( 'model' => self::clip_model( '' !== $model ? $model : $response_model ), 'response_model' => self::clip_model( $response_model ), 'usage' => $usage );
	}

	/** De una respuesta enorme (imagen en base64), sólo el último bloque `usage` / `usageMetadata`, su modelo y cuántas imágenes. */
	private static function usage_slice( $body ) {
		$out = array( 'images' => substr_count( $body, '"b64_json"' ) ?: substr_count( $body, '"url"' ) );
		foreach ( array( 'usageMetadata', 'usage' ) as $key ) {
			$at = strrpos( $body, '"' . $key . '"' );
			if ( false === $at ) {
				continue;
			}
			$start = strpos( $body, '{', $at );
			if ( false === $start ) {
				continue;
			}
			$depth = 0;
			$len   = strlen( $body );
			for ( $i = $start; $i < $len && $i < $start + 8192; $i++ ) {
				if ( '{' === $body[ $i ] ) {
					$depth++;
				} elseif ( '}' === $body[ $i ] && 0 === --$depth ) {
					$block = json_decode( substr( $body, $start, $i - $start + 1 ), true );
					if ( is_array( $block ) ) {
						$out[ $key ] = $block;
					}
					break;
				}
			}
			if ( isset( $out[ $key ] ) ) {
				break;
			}
		}
		if ( preg_match( '/"model"\s*:\s*"([^"\\\\]{1,120})"/', substr( $body, 0, 4096 ), $m ) ) {
			$out['model'] = $m[1];
		}
		return $out;
	}

	private static function clip_model( $model ) {
		return substr( (string) preg_replace( '/[^A-Za-z0-9._:\/@+-]/', '', (string) $model ), 0, 80 );
	}

	/**
	 * Guarda una llamada: `feature`, `provider`, `model`, `response_model`, `usage`, `status`, `is_error` y `is_retry`.
	 * El costo se calcula aquí con la tabla de precios vigente (null = sin precio). Devuelve la fila guardada.
	 */
	public static function record( array $call ) {
		$usage   = wp_parse_args( (array) ( $call['usage'] ?? array() ), array( 'input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0, 'cache_write_1h' => 0, 'units' => 0 ) );
		$model   = (string) ( $call['model'] ?? '' );
		$price   = Digitalisimo_AI_Usage_Prices::find( $model, (string) ( $call['response_model'] ?? '' ) );
		$feature = sanitize_key( (string) ( $call['feature'] ?? 'other' ) );
		$row     = array(
			'created'               => gmdate( 'Y-m-d H:i:s' ),
			'day'                   => self::today(),
			'blog_id'               => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1,
			'feature'               => substr( '' !== $feature ? $feature : 'other', 0, 32 ),
			'user_id'               => (int) get_current_user_id(),
			'provider'              => substr( sanitize_key( (string) ( $call['provider'] ?? '' ) ), 0, 20 ),
			'model'                 => self::clip_model( $model ),
			'input_tokens'          => (int) $usage['input'],
			'output_tokens'         => (int) $usage['output'],
			'cache_read_tokens'     => (int) $usage['cache_read'],
			'cache_write_tokens'    => (int) $usage['cache_write'],
			'cache_write_1h_tokens' => (int) $usage['cache_write_1h'],
			'units'                 => (int) $usage['units'],
			'cost_usd'              => Digitalisimo_AI_Usage_Prices::cost( $price, $usage ),
			'status'                => (int) ( $call['status'] ?? 0 ),
			'is_error'              => ! empty( $call['is_error'] ),
			'is_retry'              => ! empty( $call['is_retry'] ),
		);
		Digitalisimo_AI_Usage_Store::insert( $row );
		self::$spend = array();
		self::after_record();
		do_action( 'digitalisimo_ai_usage_recorded', $row );
		return $row;
	}

	/* ---------------- Fechas ---------------- */

	/** Hoy en la zona horaria del sitio. */
	public static function today() {
		return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : current_time( 'Y-m-d' );
	}

	/** Días locales de los periodos: hoy, los últimos 7 días (con hoy), el mes en curso. */
	public static function days() {
		$today = self::today();
		$time  = strtotime( $today . ' 00:00:00 UTC' );
		return array( 'today' => $today, 'week' => gmdate( 'Y-m-d', $time - 6 * 86400 ), 'month' => substr( $today, 0, 8 ) . '01' );
	}

	/** Un día válido (Y-m-d) o el de respaldo. */
	public static function date_or( $value, $fallback ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && checkdate( (int) substr( $value, 5, 2 ), (int) substr( $value, 8, 2 ), (int) substr( $value, 0, 4 ) ) ? $value : $fallback;
	}

	/* ---------------- Ajustes (sitio → red → fábrica) ---------------- */

	public static function defaults() {
		return array( 'retention_days' => 90, 'mxn_rate' => 0, 'budget_enabled' => 0, 'budget_usd' => 0, 'budget_action' => 'notify', 'budget_email' => '', 'purge_on_uninstall' => 0 );
	}

	/** Lo guardado en este sitio; en una red, un sitio que nunca guardó hereda de la red. */
	public static function local() {
		$o = get_option( self::SETTINGS, array() );
		$o = is_array( $o ) ? $o : array();
		if ( is_multisite() && ! array_key_exists( 'inherit_network', $o ) ) {
			$o['inherit_network'] = 1;
		}
		return $o;
	}

	public static function network() {
		$o = is_multisite() ? get_site_option( self::SETTINGS, array() ) : array();
		return wp_parse_args( is_array( $o ) ? $o : array(), self::defaults() );
	}

	public static function inherits() {
		return is_multisite() && ! empty( self::local()['inherit_network'] );
	}

	/** Ajustes efectivos del sitio en curso. */
	public static function settings( $key = null ) {
		$o = self::inherits() ? self::network() : wp_parse_args( self::local(), self::defaults() );
		return null === $key ? $o : ( $o[ $key ] ?? null );
	}

	/** Valida los ajustes enviados (las mismas reglas en el sitio y en la red). */
	public static function sanitize_settings( $in, array $base ) {
		$in  = is_array( $in ) ? $in : array();
		$out = wp_parse_args( $base, self::defaults() );
		if ( array_key_exists( 'retention_days', $in ) ) {
			$out['retention_days'] = max( 7, min( 3650, absint( $in['retention_days'] ) ?: 90 ) );
		}
		if ( array_key_exists( 'mxn_rate', $in ) ) {
			$rate            = (float) str_replace( ',', '.', (string) $in['mxn_rate'] );
			$out['mxn_rate'] = $rate > 0 && $rate < 1000 ? round( $rate, 4 ) : 0;
		}
		foreach ( array( 'budget_enabled', 'purge_on_uninstall' ) as $flag ) {
			if ( array_key_exists( $flag, $in ) ) {
				$out[ $flag ] = empty( $in[ $flag ] ) ? 0 : 1;
			}
		}
		if ( array_key_exists( 'budget_usd', $in ) ) {
			$usd               = (float) str_replace( ',', '.', (string) $in['budget_usd'] );
			$out['budget_usd'] = $usd > 0 && $usd < 10000000 ? round( $usd, 2 ) : 0;
		}
		if ( array_key_exists( 'budget_action', $in ) ) {
			$out['budget_action'] = 'pause' === $in['budget_action'] ? 'pause' : 'notify';
		}
		if ( array_key_exists( 'budget_email', $in ) ) {
			$email               = sanitize_email( (string) $in['budget_email'] );
			$out['budget_email'] = is_email( $email ) ? $email : '';
		}
		if ( $out['budget_enabled'] && $out['budget_usd'] <= 0 ) {
			$out['budget_enabled'] = 0;
		}
		unset( $out['inherit_network'] );
		return $out;
	}

	/** Pesos por dólar (0 = sin tipo de cambio: sólo dólares). Ver mxn_info(). */
	public static function mxn_rate() {
		return self::mxn_info()['rate'];
	}

	/**
	 * Tipo de cambio y de dónde salió: el escrito en los ajustes de uso (los del sitio o, si hereda o en la
	 * administración de la red, los de la red) y, si no hay, el último que guardó DIGITALÍSIMO Hosting al actualizar los
	 * precios de dominios (FIX de Banxico o su respaldo) en este sitio o en el principal de la red, de hasta 90 días.
	 * Nunca consulta nada por la red. Al final, el filtro `digitalisimo_ai_usage_mxn_rate` puede darlo otro módulo.
	 *
	 * @return array{rate: float, source: string, kind: string, date: string} `rate` 0 = sólo dólares; `source`:
	 *         settings, network, hosting o filter; `kind`: banxico u other (sólo de Hosting); `date`: día local.
	 */
	public static function mxn_info() {
		$blog    = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
		$network = is_multisite() && ( ( function_exists( 'is_network_admin' ) && is_network_admin() ) || self::inherits() );
		$set     = $network && function_exists( 'is_network_admin' ) && is_network_admin() ? self::network() : self::settings();
		$info    = array( 'rate' => (float) ( $set['mxn_rate'] ?? 0 ), 'source' => $network ? 'network' : 'settings', 'kind' => '', 'date' => '' );
		if ( $info['rate'] <= 0 ) {
			$info = array( 'rate' => 0.0, 'source' => '', 'kind' => '', 'date' => '' );
			$main = is_multisite() && function_exists( 'get_main_site_id' ) ? (int) get_main_site_id() : $blog;
			$sets = array( get_option( 'digitalisimo_hosting_price_history', array() ) );
			if ( $main !== $blog && function_exists( 'get_blog_option' ) ) {
				if ( ! isset( self::$main_fx[ $main ] ) ) {
					self::$main_fx[ $main ] = get_blog_option( $main, 'digitalisimo_hosting_price_history', array() ); // Una vez por petición.
				}
				$sets[] = self::$main_fx[ $main ];
			}
			foreach ( $sets as $history ) {
				foreach ( array_reverse( is_array( $history ) ? $history : array() ) as $entry ) {
					$rate = is_array( $entry ) ? (float) ( $entry['fx'] ?? 0 ) : 0.0;
					$when = is_array( $entry ) ? (int) ( $entry['t'] ?? 0 ) : 0;
					if ( 'ok' === ( $entry['status'] ?? '' ) && $rate >= 5 && $rate <= 100 && time() - $when < 90 * 86400 ) {
						$kind = 0 === stripos( (string) ( $entry['fx_source'] ?? '' ), 'Banxico' ) ? 'banxico' : 'other';
						$info = array( 'rate' => $rate, 'source' => 'hosting', 'kind' => $kind, 'date' => function_exists( 'wp_date' ) ? (string) wp_date( 'Y-m-d', $when ) : gmdate( 'Y-m-d', $when ) );
						break 2;
					}
				}
			}
		}
		$rate = (float) apply_filters( 'digitalisimo_ai_usage_mxn_rate', $info['rate'] );
		if ( $rate !== $info['rate'] ) {
			$info = array( 'rate' => $rate, 'source' => 'filter', 'kind' => '', 'date' => '' );
		}
		$info['rate'] = $rate > 0 ? $rate : 0.0;
		return $info;
	}

	/** Un día (AAAA-MM-DD) en palabras: «26 de noviembre», con el año si no es el de hoy. */
	public static function long_date( $day ) {
		$months = array( 1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );
		$parts  = explode( '-', (string) $day );
		if ( 3 !== count( $parts ) || ! isset( $months[ (int) $parts[1] ] ) ) {
			return (string) $day;
		}
		return (int) $parts[2] . ' de ' . $months[ (int) $parts[1] ] . ( substr( self::today(), 0, 4 ) !== $parts[0] ? ' de ' . $parts[0] : '' );
	}

	/* ---------------- Presupuesto ---------------- */

	/** Gasto del mes en curso (memorizado en la petición). */
	public static function month_spend() {
		$days = self::days();
		$key  = ( function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1 ) . '|' . $days['month'];
		if ( ! isset( self::$spend[ $key ] ) ) {
			self::$spend[ $key ] = Digitalisimo_AI_Usage_Store::spend_since( $days['month'] );
		}
		return self::$spend[ $key ];
	}

	/** Estado del presupuesto: `enabled`, `cap`, `spent`, `percent`, `level` (0, 80 o 100), `action` y `paused`. */
	public static function budget() {
		$s       = self::settings();
		$enabled = ! empty( $s['budget_enabled'] ) && (float) $s['budget_usd'] > 0;
		if ( ! $enabled ) {
			return array( 'enabled' => false, 'cap' => 0.0, 'spent' => 0.0, 'percent' => 0.0, 'level' => 0, 'action' => (string) $s['budget_action'], 'paused' => false );
		}
		$cap     = (float) $s['budget_usd'];
		$spent   = self::month_spend();
		$percent = $cap > 0 ? $spent / $cap * 100 : 0;
		$level   = $percent >= 100 ? 100 : ( $percent >= 80 ? 80 : 0 );
		return array( 'enabled' => true, 'cap' => $cap, 'spent' => $spent, 'percent' => round( $percent, 1 ), 'level' => $level, 'action' => (string) $s['budget_action'], 'paused' => 100 === $level && 'pause' === $s['budget_action'] );
	}

	/** Las funciones de IA de este sitio están en pausa por el presupuesto (sin presupuesto, nunca consulta la base). */
	public static function paused() {
		$s = self::settings();
		if ( empty( $s['budget_enabled'] ) || 'pause' !== ( $s['budget_action'] ?? '' ) || (float) ( $s['budget_usd'] ?? 0 ) <= 0 ) {
			return false;
		}
		return self::month_spend() >= (float) $s['budget_usd'];
	}

	public static function pause_message() {
		$cap = (float) self::settings( 'budget_usd' );
		return sprintf( 'Las funciones de IA de este sitio están en pausa: se alcanzó el presupuesto mensual de US$ %s. Se reanudan el día 1 del próximo mes, o antes si el administrador sube el tope en Digitalisimo → Uso de IA.', number_format( $cap, 2 ) );
	}

	/** `pre_http_request`: con el sitio en pausa, la llamada de IA Tools no sale. */
	public static function gate( $pre, $args = array(), $url = '' ) {
		if ( false !== $pre ) {
			return $pre;
		}
		try {
			if ( '' !== self::provider_for( $url, is_array( $args ) ? $args : array() ) && self::paused() ) {
				return new WP_Error( 'digitalisimo_ai_budget_paused', self::pause_message(), array( 'status' => 429 ) );
			}
		} catch ( Throwable $e ) {
			unset( $e );
		}
		return $pre;
	}

	/**
	 * Tras registrar: si el gasto cruzó el 80 % o el 100 % del tope por primera vez este mes, lo anota y programa el
	 * correo (en una tarea aparte: el chatbot no espera al servidor de correo).
	 */
	private static function after_record() {
		$budget = self::budget();
		if ( ! $budget['enabled'] || ! $budget['level'] ) {
			return;
		}
		$month  = substr( self::today(), 0, 7 );
		$alerts = get_option( self::ALERTS, array() );
		$alerts = is_array( $alerts ) && ( $alerts['month'] ?? '' ) === $month ? $alerts : array( 'month' => $month, 'sent' => array(), 'pending' => array() );
		if ( in_array( $budget['level'], (array) $alerts['sent'], true ) || in_array( $budget['level'], (array) $alerts['pending'], true ) ) {
			return;
		}
		$alerts['pending'][] = $budget['level'];
		update_option( self::ALERTS, $alerts, false );
		if ( function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( self::CRON_ALERT ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_ALERT );
		}
	}

	/** Envía los avisos pendientes por correo al administrador (o al correo elegido). */
	public static function send_alerts() {
		$alerts = get_option( self::ALERTS, array() );
		if ( ! is_array( $alerts ) || empty( $alerts['pending'] ) ) {
			return;
		}
		$budget = self::budget();
		$to     = (string) self::settings( 'budget_email' );
		$to     = '' !== $to ? $to : (string) get_option( 'admin_email' );
		$site   = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$sent   = (array) ( $alerts['sent'] ?? array() );
		foreach ( array_unique( array_map( 'intval', (array) $alerts['pending'] ) ) as $level ) {
			$subject = sprintf( '[%s] IA: %s del presupuesto mensual', $site, 100 === $level ? 'se alcanzó el 100 %' : 'se usó el 80 %' );
			$body    = sprintf( "El gasto estimado de IA de %s este mes es de US$ %s de un tope de US$ %s (%s %%).\n\n", home_url( '/' ), number_format( $budget['spent'], 2 ), number_format( $budget['cap'], 2 ), number_format( $budget['percent'], 1 ) );
			$body   .= 100 === $level && 'pause' === $budget['action'] ? "Las funciones de IA del sitio quedaron en pausa hasta el día 1 o hasta que subas el tope.\n" : ( 100 === $level ? "Las funciones de IA siguen activas: el presupuesto está configurado para sólo avisar.\n" : "Al llegar al 100 % se aplicará lo configurado en el presupuesto.\n" );
			$body   .= "\nDetalle: " . admin_url( 'admin.php?page=' . Digitalisimo_AI_Usage_Admin::PAGE ) . "\n";
			if ( '' !== $to ) {
				wp_mail( $to, $subject, $body );
			}
			$sent[] = $level;
		}
		$alerts['sent']    = array_values( array_unique( $sent ) );
		$alerts['pending'] = array();
		update_option( self::ALERTS, $alerts, false );
	}

	/* ---------------- Retención ---------------- */

	public static function schedule() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_PURGE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_PURGE );
		}
	}

	/** Borra los detalles más viejos que la retención; el resumen diario se conserva. */
	public static function purge() {
		$days   = max( 7, (int) self::settings( 'retention_days' ) );
		$before = gmdate( 'Y-m-d', strtotime( self::today() . ' 00:00:00 UTC' ) - $days * 86400 );
		return Digitalisimo_AI_Usage_Store::purge( $before );
	}

	/* ---------------- Resumen (pantalla, CSV y Ability) ---------------- */

	/** Totales de hoy, 7 días, el mes y un rango, el desglose del rango por función y modelo, y el presupuesto. */
	public static function summary( $from = '', $to = '' ) {
		$days  = self::days();
		$from  = self::date_or( $from, $days['month'] );
		$to    = self::date_or( $to, $days['today'] );
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}
		$fx    = self::mxn_info();
		$rate  = $fx['rate'];
		$money = function ( array $t ) use ( $rate ) {
			$t['cost_mxn'] = $rate > 0 ? round( $t['cost_usd'] * $rate, 4 ) : null;
			return $t;
		};
		$rows  = function ( $by ) use ( $from, $to, $money ) {
			$out = array();
			foreach ( Digitalisimo_AI_Usage_Store::breakdown( $by, $from, $to ) as $row ) {
				$out[] = $money( $row );
			}
			return $out;
		};
		$budget = self::budget();
		return array(
			'site'       => home_url( '/' ),
			'blog_id'    => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1,
			'currency'   => 'USD',
			'mxn_rate'   => $rate > 0 ? $rate : null,
			'mxn_source' => $rate > 0 ? self::fx_text( $fx ) : '',
			'periods'    => array(
				'today' => $money( Digitalisimo_AI_Usage_Store::totals( $days['today'], $days['today'] ) ) + array( 'from' => $days['today'], 'to' => $days['today'] ),
				'week'  => $money( Digitalisimo_AI_Usage_Store::totals( $days['week'], $days['today'] ) ) + array( 'from' => $days['week'], 'to' => $days['today'] ),
				'month' => $money( Digitalisimo_AI_Usage_Store::totals( $days['month'], $days['today'] ) ) + array( 'from' => $days['month'], 'to' => $days['today'] ),
				'range' => $money( Digitalisimo_AI_Usage_Store::totals( $from, $to ) ) + array( 'from' => $from, 'to' => $to ),
			),
			'by_feature' => $rows( 'feature' ),
			'by_model'   => $rows( 'model' ),
			'unpriced'   => Digitalisimo_AI_Usage_Store::unpriced_models( $from, $to ),
			'budget'     => array( 'enabled' => $budget['enabled'], 'cap_usd' => $budget['cap'], 'spent_usd' => $budget['spent'], 'percent' => $budget['percent'], 'action' => $budget['action'], 'paused' => $budget['paused'] ),
		);
	}

	/** De dónde salió el tipo de cambio, en palabras para el dueño. */
	public static function fx_text( array $fx ) {
		switch ( $fx['source'] ) {
			case 'settings':
				return 'el que escribiste en Ajustes';
			case 'network':
				return 'el de los ajustes de la red';
			case 'filter':
				return 'el que da otro módulo del sitio';
		}
		return ( 'banxico' === $fx['kind'] ? 'el FIX de Banxico' : 'el tipo de cambio de referencia' ) . ' que guardó DIGITALÍSIMO Hosting' . ( '' !== $fx['date'] ? ' el ' . self::long_date( $fx['date'] ) : '' );
	}

	public static function feature_label( $feature ) {
		return self::FEATURES[ $feature ] ?? ( '' !== (string) $feature ? ucfirst( (string) $feature ) : self::FEATURES['other'] );
	}

	/* ---------------- Ability de MCP: digitalisimo-ia/usage ---------------- */

	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) || ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'digitalisimo-ia' ) ) ) {
			return;
		}
		wp_register_ability_category( 'digitalisimo-ia', array( 'label' => 'DIGITALÍSIMO IA Tools', 'description' => 'Funciones de IA Tools de este sitio.' ) );
	}

	public static function register_ability() {
		if ( ! function_exists( 'wp_register_ability' ) || ( function_exists( 'wp_has_ability' ) && wp_has_ability( self::ABILITY ) ) ) {
			return;
		}
		wp_register_ability( self::ABILITY, self::ability_definition() );
	}

	public static function ability_definition() {
		$total = array( 'type' => 'object' );
		return array(
			'label'               => 'Uso y gasto de IA del sitio',
			'description'         => 'Cuánto lleva gastado este sitio en IA (USD y, si el sitio tiene tipo de cambio, MXN), con un resumen en palabras (`resumen`): totales de hoy, de los últimos 7 días, del mes y de un rango, con su desglose por función y por modelo, las llamadas de modelos sin precio y el estado del presupuesto. Sólo lectura: nunca devuelve claves, prompts ni respuestas.',
			'category'            => 'digitalisimo-ia',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'properties'           => array(
					'from' => array( 'type' => 'string', 'description' => 'Primer día del rango (AAAA-MM-DD, hora del sitio). Por omisión, el día 1 del mes.' ),
					'to'   => array( 'type' => 'string', 'description' => 'Último día del rango (AAAA-MM-DD). Por omisión, hoy.' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'site'       => array( 'type' => 'string' ),
					'blog_id'    => array( 'type' => 'integer' ),
					'currency'   => array( 'type' => 'string' ),
					'mxn_rate'   => array( 'type' => array( 'number', 'null' ) ),
					'mxn_source' => array( 'type' => 'string' ),
					'resumen'    => array( 'type' => 'string', 'description' => 'El gasto en una frase, para contestarle al dueño.' ),
					'periods'    => array( 'type' => 'object', 'properties' => array( 'today' => $total, 'week' => $total, 'month' => $total, 'range' => $total ) ),
					'by_feature' => array( 'type' => 'array' ),
					'by_model'   => array( 'type' => 'array' ),
					'unpriced'   => array( 'type' => 'object' ),
					'budget'     => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => array( __CLASS__, 'ability_execute' ),
			'permission_callback' => array( __CLASS__, 'ability_permission' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			),
		);
	}

	public static function ability_permission( $input = null ) {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	public static function ability_execute( $input = null ) {
		$input   = is_array( $input ) ? $input : array();
		$summary = self::summary( (string) ( $input['from'] ?? '' ), (string) ( $input['to'] ?? '' ) );
		foreach ( array( 'by_feature', 'by_model' ) as $list ) {
			foreach ( $summary[ $list ] as $i => $row ) {
				if ( isset( $row['feature'] ) ) {
					$summary[ $list ][ $i ]['label'] = self::feature_label( $row['feature'] );
				}
			}
		}
		$summary['resumen'] = self::plain_summary( $summary );
		return $summary;
	}

	/**
	 * El gasto en una frase para el dueño (lo que contesta la IA desde el MCP): el mes, hoy y en qué se fue el rango.
	 * Sólo cifras y nombres de funciones.
	 */
	public static function plain_summary( array $summary ) {
		$rate  = (float) ( $summary['mxn_rate'] ?? 0 );
		$money = function ( $usd ) use ( $rate ) {
			$usd  = (float) $usd;
			$text = $usd > 0 && $usd < 0.005 ? 'menos de US$ 0.01' : 'US$ ' . number_format( $usd, 2 );
			return $text . ( $rate > 0 ? ' (unos MX$ ' . number_format( $usd * $rate, 2 ) . ')' : '' );
		};
		$p     = $summary['periods'];
		$text  = 'Este mes (desde el ' . self::long_date( $p['month']['from'] ) . ') la IA de este sitio lleva ' . $money( $p['month']['cost_usd'] ) . ' en ' . number_format( (int) $p['month']['calls'] ) . ' consultas; hoy, ' . $money( $p['today']['cost_usd'] ) . '.';
		$parts = array();
		$rows  = array_values(
			array_filter(
				(array) $summary['by_feature'],
				function ( $row ) {
					return (float) $row['cost_usd'] > 0 || ! empty( $row['unpriced'] );
				}
			)
		);
		foreach ( array_slice( $rows, 0, 6 ) as $row ) {
			$parts[] = self::feature_label( $row['feature'] ?? '' ) . ' ' . $money( $row['cost_usd'] );
		}
		if ( $parts ) {
			$text .= ' Del ' . self::long_date( $p['range']['from'] ) . ' al ' . self::long_date( $p['range']['to'] ) . ' se fue en: ' . implode( '; ', $parts ) . ( count( $rows ) > 6 ? '; y otras ' . ( count( $rows ) - 6 ) . ' funciones con menos gasto' : '' ) . '.';
		}
		if ( ! empty( $summary['unpriced'] ) ) {
			$text .= ' Hay llamadas de modelos sin precio registrado: el total real es algo mayor.';
		}
		if ( ! empty( $summary['budget']['enabled'] ) ) {
			$text .= ' Tope del mes: ' . $money( $summary['budget']['cap_usd'] ) . ' (' . number_format( (float) $summary['budget']['percent'], 1 ) . ' % usado' . ( ! empty( $summary['budget']['paused'] ) ? ', funciones de IA en pausa' : '' ) . ').';
		}
		$text .= $rate > 0 ? ' Pesos al tipo de cambio ' . number_format( $rate, 4 ) . ', ' . $summary['mxn_source'] . '.' : ' Montos en dólares (el sitio no tiene tipo de cambio).';
		return $text . ' Cifras estimadas con los precios publicados de cada proveedor.';
	}

	/** El servidor MCP de DIGITALÍSIMO Elements la ofrece junto a las suyas. */
	public static function server_tools( $tools ) {
		$tools = is_array( $tools ) ? $tools : array();
		if ( function_exists( 'wp_register_ability' ) && ! in_array( self::ABILITY, $tools, true ) ) {
			$tools[] = self::ABILITY;
		}
		return $tools;
	}
}
