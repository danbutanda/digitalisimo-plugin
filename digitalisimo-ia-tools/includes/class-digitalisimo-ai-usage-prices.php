<?php
defined( 'ABSPATH' ) || exit;

/**
 * Precios de los proveedores de IA para el registro de uso (docs/IA_USAGE.md).
 *
 * Todos en dólares (USD) por millón de tokens, salvo `image`, que es por imagen. Por modelo:
 * - `input`: tokens de entrada cobrados completos (sin caché);
 * - `output`: tokens de salida (en los modelos de imagen, los de la imagen generada; en Gemini incluye el razonamiento);
 * - `cache_read`: tokens leídos de la caché;
 * - `cache_write`: tokens escritos en la caché por 5 minutos (Anthropic); `cache_write_1h`: por una hora;
 * - `image`: precio por imagen cuando el proveedor no devuelve tokens de la imagen;
 * - `long_over` y `long`: precios que rigen cuando la entrada de la petición (entrada + caché) pasa de `long_over` tokens.
 * Un precio que falta se toma del de entrada (lo más prudente), salvo `cache_write_1h`, que se toma de `cache_write`.
 *
 * Cada fila dice de dónde salió (`source`) y cuándo se comprobó (`checked`). El administrador corrige o agrega precios
 * en IA Tools → Uso de IA → Precios (opción del sitio y, en una red, de la red): su valor manda sobre esta tabla. Un
 * modelo sin precio se registra igual, con costo «sin precio», y la pantalla lo avisa.
 */
final class Digitalisimo_AI_Usage_Prices {
	const OPTION = 'digitalisimo_ai_usage_prices';
	const FIELDS = array( 'input', 'output', 'cache_read', 'cache_write', 'cache_write_1h', 'image' );

	const SRC_ANTHROPIC = 'https://platform.claude.com/docs/en/about-claude/pricing';
	const SRC_OPENAI    = 'https://developers.openai.com/api/docs/pricing';
	const SRC_GOOGLE    = 'https://ai.google.dev/gemini-api/docs/pricing';
	const SRC_DEEPSEEK  = 'https://api-docs.deepseek.com/quick_start/pricing';
	const SRC_XAI       = 'https://docs.x.ai/docs/models';

	/** Precios de fábrica. Al cambiar una fila, cambiar también `checked` y la tabla de docs/IA_USAGE.md. */
	public static function builtin() {
		$a  = array( 'provider' => 'anthropic', 'source' => self::SRC_ANTHROPIC, 'checked' => '2026-11-26' );
		$o  = array( 'provider' => 'openai', 'source' => self::SRC_OPENAI, 'checked' => '2026-11-26', 'note' => 'Comprobado por búsqueda: la página oficial no se dejó leer directamente; confírmalo.' );
		$g  = array( 'provider' => 'google', 'source' => self::SRC_GOOGLE, 'checked' => '2026-11-26', 'note' => 'Comprobado por búsqueda (Gemini Developer API, nivel de pago): confírmalo.' );
		$d  = array( 'provider' => 'deepseek', 'source' => self::SRC_DEEPSEEK, 'checked' => '2026-11-26', 'note' => 'Precio en horas pico (el más alto). Fuera de pico cuesta la mitad.' );
		$x  = array( 'provider' => 'xai', 'source' => self::SRC_XAI, 'checked' => '2026-11-26' );
		$an = function ( $in, $out, $read, $w5, $w1 ) use ( $a ) {
			return $a + array( 'input' => $in, 'output' => $out, 'cache_read' => $read, 'cache_write' => $w5, 'cache_write_1h' => $w1 );
		};
		$opus4 = $an( 5, 25, 0.50, 6.25, 10 );
		$son4  = $an( 3, 15, 0.30, 3.75, 6 );
		$fab51 = $an( 10, 50, 0.25, 12.5, 20 );
		$fab52 = $an( 8, 40, 0.20, 10, 16 );
		$fab5  = $an( 10, 50, 1, 12.5, 20 );
		$grok  = $x + array( 'input' => 2, 'cache_read' => 0.50, 'output' => 6, 'long_over' => 200000, 'long' => array( 'input' => 4, 'cache_read' => 1, 'output' => 12 ) );
		return array(
			// Anthropic (Claude API, precios estándar; sin modo rápido ni inference_geo, que IA Tools no usa).
			'claude-fable-5-2'   => $fab52,
			'claude-mythos-5-2'  => $fab52,
			'claude-fable-5-1'   => $fab51,
			'claude-mythos-5-1'  => $fab51,
			'claude-fable-5'     => $fab5,
			'claude-mythos-5'    => $fab5,
			'claude-opus-5-5'    => $an( 4, 20, 0.20, 5, 8 ),
			'claude-opus-5'      => $opus4,
			'claude-opus-4-8'    => $opus4,
			'claude-opus-4-7'    => $opus4,
			'claude-opus-4-6'    => $opus4,
			'claude-opus-4-5'    => $opus4,
			'claude-opus-4-1'    => $an( 15, 75, 1.50, 18.75, 30 ),
			'claude-sonnet-5-5'  => $an( 2, 10, 0.10, 2.5, 4 ),
			'claude-sonnet-5'    => $an( 2, 10, 0.20, 2.5, 4 ),
			'claude-sonnet-4-6'  => $son4,
			'claude-sonnet-4-5'  => $son4,
			'claude-sonnet-4-0'  => $son4,
			'claude-haiku-5-5'   => $an( 0.10, 0.50, 0.01, 0.125, 0.20 ) + array( 'long_over' => 100000, 'long' => array( 'input' => 0.50, 'output' => 2.50, 'cache_read' => 0.05, 'cache_write' => 0.625, 'cache_write_1h' => 1 ) ),
			'claude-haiku-4-5'   => $an( 1, 5, 0.10, 1.25, 2 ),
			// OpenAI (nivel Standard). En los modelos de imagen, `input` es el texto del prompt y `output` la imagen.
			'gpt-4o-mini'            => $o + array( 'input' => 0.15, 'cache_read' => 0.075, 'output' => 0.60 ),
			'gpt-4o'                 => $o + array( 'input' => 2.50, 'cache_read' => 1.25, 'output' => 10 ),
			'gpt-4.1'                => $o + array( 'input' => 2, 'cache_read' => 0.50, 'output' => 8 ),
			'gpt-4.1-mini'           => $o + array( 'input' => 0.40, 'cache_read' => 0.10, 'output' => 1.60 ),
			'text-embedding-3-small' => $o + array( 'input' => 0.02, 'output' => 0 ),
			'text-embedding-3-large' => $o + array( 'input' => 0.13, 'output' => 0 ),
			'gpt-image-1'            => $o + array( 'input' => 5, 'cache_read' => 1.25, 'output' => 40, 'image' => 0.063 ),
			'gpt-image-1-mini'       => $o + array( 'input' => 2, 'cache_read' => 0.20, 'output' => 8, 'image' => 0.015 ),
			'gpt-image-1.5'          => $o + array( 'input' => 5, 'cache_read' => 1.25, 'output' => 32, 'image' => 0.05 ),
			'gpt-image-2'            => array( 'note' => 'Salida de imagen de la página de modelos de OpenAI; el texto de entrada, de listados de terceros: confírmalo.' ) + $o + array( 'input' => 5, 'cache_read' => 1.25, 'output' => 30, 'image' => 0.041 ),
			// Google (Gemini Developer API). La salida incluye los tokens de razonamiento.
			'gemini-2.5-flash'      => $g + array( 'input' => 0.30, 'cache_read' => 0.03, 'output' => 2.50 ),
			'gemini-2.5-flash-lite' => $g + array( 'input' => 0.10, 'cache_read' => 0.01, 'output' => 0.40 ),
			'gemini-2.5-pro'        => $g + array( 'input' => 1.25, 'cache_read' => 0.125, 'output' => 10, 'long_over' => 200000, 'long' => array( 'input' => 2.50, 'cache_read' => 0.25, 'output' => 15 ) ),
			// DeepSeek (precio de horas pico; la página ya no lista deepseek-chat).
			'deepseek-flash'   => $d + array( 'input' => 0.30, 'cache_read' => 0.006, 'output' => 1.20 ),
			'deepseek-v4-pro'  => $d + array( 'input' => 1.32, 'cache_read' => 0.044, 'output' => 3.96 ),
			// xAI (la página ya no lista grok-4 ni grok-3-mini).
			'grok-4.7' => $grok,
			'grok-4.6' => $grok,
			'grok-4.5' => array( 'cache_read' => 0.30, 'long' => array( 'input' => 4, 'cache_read' => 0.60, 'output' => 12 ) ) + $grok,
			'grok-4.3' => $x + array( 'input' => 1.25, 'cache_read' => 0.20, 'output' => 2.50, 'long_over' => 200000, 'long' => array( 'input' => 2.50, 'cache_read' => 0.40, 'output' => 5 ) ),
		);
	}

	/** Correcciones guardadas: las del sitio (si no hereda la red) sobre las de la red (en una red). */
	public static function overrides() {
		$out      = array();
		$multi    = function_exists( 'is_multisite' ) && is_multisite();
		// En la administración de la red sólo cuentan los de la red; un sitio que hereda, igual.
		$inherits = $multi && ( ( function_exists( 'is_network_admin' ) && is_network_admin() ) || ( class_exists( 'Digitalisimo_AI_Usage' ) && Digitalisimo_AI_Usage::inherits() ) );
		if ( $multi ) {
			$out = self::clean( get_site_option( self::OPTION, array() ) );
		}
		if ( ! $inherits ) {
			foreach ( self::clean( get_option( self::OPTION, array() ) ) as $model => $row ) {
				$out[ $model ] = array_merge( $out[ $model ] ?? array(), $row );
			}
		}
		return $out;
	}

	/** Las correcciones de una superficie concreta (la red o este sitio), para pintarlas en su formulario. */
	public static function stored( $network ) {
		return self::clean( $network ? get_site_option( self::OPTION, array() ) : get_option( self::OPTION, array() ) );
	}

	/** Toda la tabla vigente: la de fábrica con las correcciones encima (`origin`: builtin, custom o edited). */
	public static function all() {
		$table = array();
		foreach ( self::builtin() as $model => $row ) {
			$table[ $model ] = $row + array( 'origin' => 'builtin' );
		}
		foreach ( self::overrides() as $model => $row ) {
			$base             = $table[ $model ] ?? array();
			$table[ $model ]  = array_merge( $base, $row, array( 'origin' => $base ? 'edited' : 'custom' ) );
		}
		ksort( $table );
		return $table;
	}

	/** El ID con el que se busca un modelo: minúsculas, sin `models/`, sin fecha ni `-latest` al final. */
	public static function normalize( $model ) {
		$model = strtolower( trim( (string) $model ) );
		$model = (string) preg_replace( '#^models/#', '', $model );
		$model = (string) preg_replace( '/-(latest|\d{4}-\d{2}-\d{2}|\d{8})$/', '', $model );
		return $model;
	}

	/** El precio de un modelo (o de su versión de la respuesta), o null si no se conoce. */
	public static function find( $model, $alt = '' ) {
		static $all = null;
		if ( null === $all || ! empty( $GLOBALS['digitalisimo_ai_usage_prices_reset'] ) ) {
			$all = self::all();
			unset( $GLOBALS['digitalisimo_ai_usage_prices_reset'] );
		}
		foreach ( array( $model, $alt ) as $candidate ) {
			foreach ( array( strtolower( trim( (string) $candidate ) ), self::normalize( $candidate ) ) as $key ) {
				if ( '' !== $key && isset( $all[ $key ] ) ) {
					return $all[ $key ];
				}
			}
		}
		return null;
	}

	/** Olvida la tabla calculada (tras guardar precios). */
	public static function reset() {
		$GLOBALS['digitalisimo_ai_usage_prices_reset'] = true;
	}

	/**
	 * Costo en USD de un uso normalizado (`input`, `output`, `cache_read`, `cache_write`, `cache_write_1h`, `units`),
	 * o null sin precio. `cache_write` es el total escrito en la caché; `cache_write_1h`, la parte escrita por una hora.
	 */
	public static function cost( $price, array $usage ) {
		if ( ! is_array( $price ) ) {
			return null;
		}
		$n      = function ( $key ) use ( $usage ) {
			return max( 0, (int) ( $usage[ $key ] ?? 0 ) );
		};
		$prompt = $n( 'input' ) + $n( 'cache_read' ) + $n( 'cache_write' );
		if ( ! empty( $price['long_over'] ) && $prompt > (int) $price['long_over'] && is_array( $price['long'] ?? null ) ) {
			$price = array_merge( $price, $price['long'] );
		}
		$rate  = function ( $key, $fallback ) use ( $price ) {
			return isset( $price[ $key ] ) && is_numeric( $price[ $key ] ) ? (float) $price[ $key ] : (float) $fallback;
		};
		$in    = $rate( 'input', 0 );
		$write = $rate( 'cache_write', $in );
		$w1h   = min( $n( 'cache_write_1h' ), $n( 'cache_write' ) );
		$usd   = ( $n( 'input' ) * $in + $n( 'output' ) * $rate( 'output', $in ) + $n( 'cache_read' ) * $rate( 'cache_read', $in ) + ( $n( 'cache_write' ) - $w1h ) * $write + $w1h * $rate( 'cache_write_1h', $write ) ) / 1000000;
		// Por imagen sólo cuando el proveedor no dio los tokens de la imagen (si los dio, ya están en la salida).
		if ( $n( 'units' ) > 0 && 0 === $n( 'output' ) && isset( $price['image'] ) && is_numeric( $price['image'] ) ) {
			$usd += $n( 'units' ) * (float) $price['image'];
		}
		return round( $usd, 8 );
	}

	/**
	 * Valida las filas del formulario de precios: `model` (ID), los seis precios (número ≥ 0 o vacío) y una nota. Una
	 * fila sin modelo o sin ningún precio se descarta; `remove` la quita.
	 */
	public static function sanitize_rows( $rows ) {
		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['remove'] ) ) {
				continue;
			}
			$model = self::normalize( sanitize_text_field( (string) ( $row['model'] ?? '' ) ) );
			if ( '' === $model || ! preg_match( '/^[a-z0-9][a-z0-9._:\/-]{0,79}$/', $model ) ) {
				continue;
			}
			$clean = array();
			foreach ( self::FIELDS as $field ) {
				$value = str_replace( ',', '.', trim( (string) ( $row[ $field ] ?? '' ) ) );
				if ( '' !== $value && is_numeric( $value ) && (float) $value >= 0 && (float) $value < 100000 ) {
					$clean[ $field ] = round( (float) $value, 6 );
				}
			}
			if ( ! $clean ) {
				continue;
			}
			$provider = sanitize_key( (string) ( $row['provider'] ?? '' ) );
			if ( '' !== $provider ) {
				$clean['provider'] = $provider;
			}
			$note = sanitize_text_field( (string) ( $row['note'] ?? '' ) );
			if ( '' !== $note ) {
				$clean['note'] = substr( $note, 0, 200 );
			}
			$clean['checked'] = gmdate( 'Y-m-d' );
			$clean['source']  = 'admin';
			$out[ $model ]    = $clean;
		}
		return $out;
	}

	private static function clean( $stored ) {
		$out = array();
		foreach ( is_array( $stored ) ? $stored : array() as $model => $row ) {
			if ( is_string( $model ) && is_array( $row ) ) {
				$out[ $model ] = $row;
			}
		}
		return $out;
	}
}
