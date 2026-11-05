<?php
namespace Digitalisimo\Elements\F7b\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Reglas de los campos condicionales del Formulario (F7b, frente J; V.9 y C2-12 b de la especificación).
 *
 * **Una sola definición**, la misma que `assets/js/forms-conditions.js`; los vectores compartidos de
 * `tests/fixtures/f7b-form-rules.json` la prueban en los dos lados:
 *
 * - **Valor:** lista de textos. Un texto se recorta con los espacios de `String.prototype.trim` (`TRIM`, que en PHP
 *   se escribe a mano para no depender de la versión de PCRE) y pasa a minúsculas (`mb_strtolower` /
 *   `toLocaleLowerCase('und')`); los vacíos se quitan. Las casillas múltiples y los `select` múltiples son listas. Un
 *   texto que no es UTF-8 válido cuenta como vacío (un navegador nunca lo envía).
 * - **Número:** `NUMBER` (signo opcional, coma o punto decimal, sin miles ni exponentes); lo no numérico nunca cumple
 *   `greater` ni `less`.
 * - **Operadores** (D20): `is`/`is_not` (algún elemento igual; con el valor vacío, «está vacío»), `contains`/
 *   `not_contains` (algún elemento lo contiene; un valor vacío no se contiene), `empty`/`not_empty`, `greater`/`less`
 *   (algún elemento numérico mayor o menor). Una regla sin campo o con un operador desconocido (de otra versión, de
 *   un kit) nunca se cumple: falla cerrada.
 * - **Campo:** `all` (todas, el valor por defecto) o `any` (alguna). Sin ninguna regla, el campo queda oculto (falla
 *   cerrado, como las condiciones nuevas de `AGENTS.md`).
 * - **Cascada:** un campo oculto cuenta como vacío para las reglas que lo miran. `hidden()` itera hasta un punto fijo
 *   (como mucho n + 1 pasadas) en el orden del formulario y con el mismo recorrido que el script: con reglas sin
 *   ciclos el resultado no depende del orden ni de los valores de los campos ocultos (el navegador no los envía).
 *   Un ciclo siempre contiene una regla que mira a un campo posterior, y el editor la avisa.
 */
final class Rules {
	/** Operadores de D20. */
	const OPERATORS = array( 'is', 'is_not', 'contains', 'not_contains', 'empty', 'not_empty', 'greater', 'less' );

	/** Tipos de campo sin reglas (C2-12 d): ocultarlos rompería el formulario o no tiene sentido. */
	const EXCLUDED = array( 'recaptcha', 'recaptcha_v3', 'honeypot', 'step', 'hidden' );

	/** Topes del saneado. */
	const MAX_RULES = 20;
	const MAX_VALUE = 200;
	const MAX_ID    = 64;

	/** Espacios de `String.prototype.trim` (WhiteSpace + LineTerminator de ECMAScript), al principio o al final. */
	const TRIM = '/^[\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+|[\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+$/u';

	/** Número con coma o punto decimal. */
	const NUMBER = '/^[+-]?(?:\d+(?:[.,]\d+)?|[.,]\d+)$/D';

	/**
	 * Reglas saneadas de un campo del repetidor `form_fields`, o `null` si el campo no es condicional.
	 *
	 * Lee los ajustes tal como llegan (con o sin los controles registrados: sin ellos el núcleo deja pasar las claves
	 * desconocidas del repetidor) y aplica las mismas condiciones que los controles: interruptor en `yes` y un tipo
	 * fuera de `EXCLUDED`.
	 *
	 * @param mixed $item Fila de `form_fields`.
	 * @return array{i:string,l:string,r:array<int,array{0:string,1:string,2:string}>}|null
	 */
	public static function from_item( $item ): ?array {
		if ( ! is_array( $item ) || 'yes' !== ( $item['digitalisimo_conditional'] ?? '' ) ) {
			return null;
		}
		$type = isset( $item['field_type'] ) && is_string( $item['field_type'] ) ? $item['field_type'] : 'text';
		if ( in_array( $type, self::EXCLUDED, true ) ) {
			return null;
		}
		return array(
			'i' => self::id( $item['custom_id'] ?? '' ),
			'l' => 'any' === ( $item['digitalisimo_logic'] ?? '' ) ? 'any' : 'all',
			'r' => self::sanitize_rules( $item['digitalisimo_rules'] ?? array() ),
		);
	}

	/**
	 * Reglas de todos los campos condicionales del formulario, en su orden.
	 *
	 * @param mixed $form_fields Ajustes `form_fields` del widget.
	 * @return array<int,array>
	 */
	public static function defs( $form_fields ): array {
		$defs = array();
		foreach ( is_array( $form_fields ) ? $form_fields : array() as $item ) {
			$def = self::from_item( $item );
			if ( null !== $def ) {
				$defs[] = $def;
			}
		}
		return $defs;
	}

	/** ¿Algún campo del formulario es condicional? */
	public static function has_conditional( $form_fields ): bool {
		foreach ( is_array( $form_fields ) ? $form_fields : array() as $item ) {
			if ( null !== self::from_item( $item ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Reglas saneadas: como mucho `MAX_RULES`, cada una `[ campo, operador, valor ]`. Un campo vacío o un operador
	 * desconocido dejan el operador en '' (la regla nunca se cumple); el valor es texto de `MAX_VALUE` caracteres.
	 *
	 * @param mixed $rules Filas del repetidor `digitalisimo_rules`.
	 * @return array<int,array{0:string,1:string,2:string}>
	 */
	public static function sanitize_rules( $rules ): array {
		$out = array();
		foreach ( is_array( $rules ) ? $rules : array() as $rule ) {
			if ( count( $out ) >= self::MAX_RULES ) {
				break;
			}
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$field = self::id( $rule['field'] ?? '' );
			$op    = isset( $rule['operator'] ) && is_string( $rule['operator'] ) && in_array( $rule['operator'], self::OPERATORS, true ) ? $rule['operator'] : '';
			$value = isset( $rule['value'] ) && is_scalar( $rule['value'] ) ? (string) $rule['value'] : '';
			if ( '' !== $value && ! preg_match( '//u', $value ) ) {
				$value = '';
			}
			$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
			if ( function_exists( 'mb_substr' ) ) {
				$value = mb_substr( $value, 0, self::MAX_VALUE, 'UTF-8' );
			}
			$out[] = array( $field, '' === $field ? '' : $op, $value );
		}
		return $out;
	}

	/** ID de campo como el del atajo `[field id="…"]` de PRO (`\w`, ASCII), con tope. */
	public static function id( $value ): string {
		$id = is_scalar( $value ) ? (string) preg_replace( '/[^A-Za-z0-9_]/', '', (string) $value ) : '';
		return substr( $id, 0, self::MAX_ID );
	}

	/** Un texto normalizado: recortado y en minúsculas; '' si no es texto o no es UTF-8 válido. */
	public static function norm( $value ): string {
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			return '';
		}
		$text = (string) $value;
		if ( '' === $text || ! preg_match( '//u', $text ) ) {
			return '';
		}
		$text = (string) preg_replace( self::TRIM, '', $text );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Valor de un campo como lista normalizada sin vacíos (un texto o una lista de casillas).
	 *
	 * @param mixed $raw Valor crudo.
	 * @return string[]
	 */
	public static function values( $raw ): array {
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array( $raw ) as $item ) {
			$text = self::norm( $item );
			if ( '' !== $text ) {
				$out[] = $text;
			}
		}
		return $out;
	}

	/** Número de un texto normalizado, o `null` si no es numérico. */
	public static function number( string $text ): ?float {
		return 1 === preg_match( self::NUMBER, $text ) ? (float) str_replace( ',', '.', $text ) : null;
	}

	/**
	 * ¿Se cumple la regla con esta lista normalizada?
	 *
	 * @param array    $rule `[ campo, operador, valor ]`.
	 * @param string[] $list Valor del campo que mira, normalizado.
	 */
	public static function test( array $rule, array $list ): bool {
		$op   = isset( $rule[1] ) && is_string( $rule[1] ) ? $rule[1] : '';
		$want = self::norm( $rule[2] ?? '' );
		switch ( $op ) {
			case 'empty':
				return ! $list;
			case 'not_empty':
				return (bool) $list;
			case 'is':
				return '' === $want ? ! $list : in_array( $want, $list, true );
			case 'is_not':
				return ! self::test( array( '', 'is', $rule[2] ?? '' ), $list );
			case 'contains':
				if ( '' === $want ) {
					return false;
				}
				foreach ( $list as $item ) {
					if ( false !== strpos( $item, $want ) ) {
						return true;
					}
				}
				return false;
			case 'not_contains':
				return ! self::test( array( '', 'contains', $rule[2] ?? '' ), $list );
			case 'greater':
			case 'less':
				$limit = self::number( $want );
				if ( null === $limit ) {
					return false;
				}
				foreach ( $list as $item ) {
					$number = self::number( $item );
					if ( null !== $number && ( 'less' === $op ? $number < $limit : $number > $limit ) ) {
						return true;
					}
				}
				return false;
		}
		return false;
	}

	/**
	 * ¿Se muestra el campo? Sin reglas, no (falla cerrado).
	 *
	 * @param array                $def    Reglas saneadas del campo (`from_item()`).
	 * @param array<string,array>  $values Valores normalizados por ID.
	 * @param array<string,bool>   $hidden Campos ocultos hasta ahora: cuentan como vacíos.
	 */
	public static function matches( array $def, array $values, array $hidden ): bool {
		$rules = isset( $def['r'] ) && is_array( $def['r'] ) ? $def['r'] : array();
		if ( ! $rules ) {
			return false;
		}
		$any = 'any' === ( $def['l'] ?? '' );
		foreach ( $rules as $rule ) {
			$field = is_array( $rule ) && isset( $rule[0] ) ? (string) $rule[0] : '';
			$list  = ! empty( $hidden[ $field ] ) || ! isset( $values[ $field ] ) ? array() : $values[ $field ];
			$ok    = is_array( $rule ) && self::test( $rule, $list );
			if ( $any && $ok ) {
				return true;
			}
			if ( ! $any && ! $ok ) {
				return false;
			}
		}
		return ! $any;
	}

	/**
	 * IDs de los campos ocultos con estos valores crudos (punto fijo, como el script).
	 *
	 * @param array $defs   Reglas de los campos condicionales en el orden del formulario (`defs()`).
	 * @param array $raw    Valores crudos por ID (texto o lista).
	 * @return string[]
	 */
	public static function hidden( array $defs, array $raw ): array {
		$values = array();
		foreach ( $raw as $id => $value ) {
			$values[ (string) $id ] = self::values( $value );
		}
		$hidden = array();
		$passes = count( $defs );
		for ( $pass = 0; $pass <= $passes; $pass++ ) {
			$changed = false;
			foreach ( $defs as $def ) {
				$id   = (string) ( $def['i'] ?? '' );
				$hide = ! self::matches( $def, $values, $hidden );
				if ( $hide !== ! empty( $hidden[ $id ] ) ) {
					$hidden[ $id ] = $hide;
					$changed       = true;
				}
			}
			if ( ! $changed ) {
				break;
			}
		}
		return array_values( array_map( 'strval', array_keys( array_filter( $hidden ) ) ) );
	}

	/**
	 * Valores con los que el navegador pinta el formulario, a partir de los ajustes (`field_value`, opciones
	 * marcadas por defecto, aceptación marcada), con las mismas comprobaciones que `Form_Base` y `Form`: así el
	 * servidor pinta ocultos los campos que el script ocultaría al cargar y no hay parpadeo. Si no coinciden (un
	 * campo de un tercero, el autocompletado del navegador), el script corrige al cargar.
	 *
	 * @param mixed $form_fields Ajustes `form_fields` del widget, ya resueltos para pintar.
	 * @return array<string,string[]> Valores crudos por ID.
	 */
	public static function defaults( $form_fields ): array {
		$out = array();
		foreach ( is_array( $form_fields ) ? $form_fields : array() as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['custom_id'] ) || ! is_scalar( $item['custom_id'] ) ) {
				continue;
			}
			$id    = (string) $item['custom_id'];
			$type  = isset( $item['field_type'] ) && is_string( $item['field_type'] ) ? $item['field_type'] : 'text';
			$value = isset( $item['field_value'] ) && is_scalar( $item['field_value'] ) ? (string) $item['field_value'] : '';
			switch ( $type ) {
				case 'select':
					$out[ $id ] = self::select_default( $item, $value );
					break;
				case 'radio':
				case 'checkbox':
					$out[ $id ] = array();
					foreach ( self::options( $item ) as $option ) {
						if ( ! empty( $value ) && $option === $value ) {
							$out[ $id ] = 'radio' === $type ? array( $option ) : array_merge( $out[ $id ], array( $option ) );
						}
					}
					break;
				case 'acceptance':
					$out[ $id ] = empty( $item['checked_by_default'] ) ? array() : array( empty( $value ) ? 'on' : $value );
					break;
				case 'upload':
				case 'html':
				case 'step':
					$out[ $id ] = array();
					break;
				default:
					$out[ $id ] = empty( $value ) ? array() : array( $value );
			}
		}
		return $out;
	}

	/** Valores de las opciones (`etiqueta|valor` o el texto), como `Form_Base::make_radio_checkbox_field()`. */
	private static function options( array $item ): array {
		$text = isset( $item['field_options'] ) && is_string( $item['field_options'] ) ? $item['field_options'] : '';
		$out  = array();
		foreach ( (array) preg_split( "/\\r\\n|\\r|\\n/", $text ) as $option ) {
			$option = (string) $option;
			if ( false !== strpos( $option, '|' ) ) {
				$parts  = explode( '|', $option );
				$option = (string) ( $parts[1] ?? '' );
			}
			$out[] = $option;
		}
		return $out;
	}

	/**
	 * Valor inicial de un `select`, como `Form_Base::make_select_field()`: marcadas las opciones cuyo valor escapado
	 * está en `field_value` separado por comas; un `select` simple sin ninguna marcada muestra la primera, y con
	 * varias, la última.
	 */
	private static function select_default( array $item, string $value ): array {
		$options  = self::options( $item );
		$multiple = ! empty( $item['allow_multiple'] );
		$picked   = array();
		foreach ( $options as $option ) {
			$escaped = function_exists( 'esc_attr' ) ? esc_attr( $option ) : htmlspecialchars( $option, ENT_QUOTES, 'UTF-8' );
			// La comparación laxa es la de PRO (`in_array()` sin el tercer argumento).
			if ( ! empty( $value ) && in_array( $escaped, explode( ',', $value ) ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				$picked[] = $option;
			}
		}
		if ( $multiple ) {
			return $picked;
		}
		if ( $picked ) {
			return array( (string) end( $picked ) );
		}
		return $options ? array( (string) $options[0] ) : array();
	}

	/** `data-digi-cond` del grupo: las reglas saneadas, en JSON compacto. */
	public static function attribute( array $def ): string {
		$flags = defined( 'JSON_UNESCAPED_UNICODE' ) ? JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES : 0;
		$json  = function_exists( 'wp_json_encode' ) ? wp_json_encode( $def, $flags ) : json_encode( $def, $flags );
		return is_string( $json ) ? $json : '{"i":"","l":"all","r":[]}';
	}
}
