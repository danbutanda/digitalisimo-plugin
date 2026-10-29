<?php
namespace Digitalisimo\Elements\F7b\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Listas cerradas de las acciones con destino (F7b, frente B; V.3 de la especificación).
 *
 * Funciones puras: no leen la base de datos ni el estado de la petición. Las usan el render (al pintar), la
 * recogida de popups y paneles de cada documento y las pruebas; `assets/js/actions.js` repite las mismas listas y
 * vuelve a validar en el navegador (el dato de un kit o de otra versión no se confía).
 *
 * Formato compacto de `data-digi-actions` (un arreglo JSON, como mucho `MAX_ROWS` filas):
 * `t` disparador, `a` acción, `g` ID del elemento destino, `p` ID del popup, `c` clase, `n` atributo, `v` valor,
 * `d` retraso en ms, `m` animación, `o` «sólo la primera vez». Sólo se escriben las claves con valor.
 *
 * En `actions.js`, la tabla `K` resume cada acción en bits: 1 necesita destino (`TARGETED`), 2 no cambia el flujo
 * (`FLOW_FREE`) y 4 muestra u oculta (`VISIBILITY`). Un cambio en estas listas se hace en los dos archivos.
 */
final class Rules {
	const TRIGGERS = array( 'click', 'hover', 'scroll_in', 'load' );

	const ACTIONS = array( 'show', 'hide', 'toggle', 'toggle_class', 'set_attribute', 'scroll_to', 'popup_open', 'popup_close', 'offcanvas_toggle', 'load_more' );

	/** Acciones que no cambian el flujo: las únicas con `scroll_in` y `load` (C2-5). */
	const FLOW_FREE = array( 'toggle_class', 'set_attribute', 'scroll_to', 'popup_open', 'popup_close', 'offcanvas_toggle' );

	/** Acciones que necesitan un elemento destino del mismo documento. */
	const TARGETED = array( 'show', 'hide', 'toggle', 'toggle_class', 'set_attribute', 'scroll_to', 'offcanvas_toggle', 'load_more' );

	/** Mostrar y ocultar: usan `hidden`, la regla de `Hidden_Rule` y `aria-expanded` en el control. */
	const VISIBILITY = array( 'show', 'hide', 'toggle' );

	/** Necesitan los paquetes de PRO (`Pro_Gate::require()`). */
	const PRO = array( 'popup_open', 'popup_close', 'offcanvas_toggle' );

	/** Acciones con animación opcional (se reproduce en el destino visible). */
	const ANIMATED = array( 'show', 'toggle', 'toggle_class', 'set_attribute', 'scroll_to' );

	/** `set_attribute`: lista cerrada (C2-7) y forma de cada valor. `hidden` y `aria-expanded` no: los gestiona mostrar/ocultar. */
	const ATTRIBUTES = array(
		'aria-pressed'    => '/^(?:true|false|mixed)$/',
		'aria-current'    => '/^(?:true|false|page|step|location|date|time)$/',
		'aria-label'      => '/^[^<>\x00-\x1f]{1,150}$/u',
		'data-digi-state' => '/^[a-z0-9_-]{1,40}$/',
	);

	/** Clases: `[a-z0-9_-]{1,40}` sin los prefijos del constructor ni de WordPress. */
	const CLASS_PATTERN  = '/^[a-z0-9_-]{1,40}$/';
	const CLASS_DENIED   = '/^(?:elementor-|e-|digi-|wp-)/';
	const ID_PATTERN     = '/^[a-z0-9]{1,24}$/i';
	const ANIMATION      = '/^[a-zA-Z]{2,40}$/';
	const MAX_DELAY      = 10000;
	const MAX_ROWS       = 20;

	/** Etiquetas que cuentan como descendiente interactivo (la misma regla que `control()` de actions.js). */
	const ALWAYS_INTERACTIVE = array( 'a', 'button', 'select', 'textarea', 'summary', 'iframe' );

	/**
	 * Fila guardada → fila compacta, o `null` si no pasa las listas cerradas. Los campos que faltan toman el valor
	 * por defecto del control (el editor no guarda los valores por defecto).
	 *
	 * @param mixed  $row     Fila del repetidor `_digitalisimo_actions`.
	 * @param string $self_id ID del elemento que dispara (destino «Este elemento»).
	 * @param array|null $ids IDs del documento (claves); `null` si no se pueden comprobar.
	 */
	public static function row( $row, string $self_id, ?array $ids = null ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$trigger = self::text( $row, 'trigger', 'click' );
		$action  = self::text( $row, 'action', 'show' );
		if ( ! in_array( $trigger, self::TRIGGERS, true ) || ! in_array( $action, self::ACTIONS, true ) ) {
			return null;
		}
		if ( 'toggle' === $action && ! in_array( $trigger, array( 'click', 'hover' ), true ) ) {
			return null;
		}
		if ( in_array( $trigger, array( 'scroll_in', 'load' ), true ) && ! in_array( $action, self::FLOW_FREE, true ) ) {
			return null;
		}
		$out = array(
			't' => $trigger,
			'a' => $action,
		);
		if ( in_array( $action, self::TARGETED, true ) ) {
			$kind   = self::text( $row, 'target_kind', 'element' );
			$target = 'self' === $kind ? $self_id : ( 'element' === $kind ? self::text( $row, 'target', '' ) : '' );
			if ( ! preg_match( self::ID_PATTERN, $target ) ) {
				return null;
			}
			if ( null !== $ids && $target !== $self_id && ! isset( $ids[ $target ] ) ) {
				return null;
			}
			$out['g'] = $target;
		}
		if ( 'popup_open' === $action ) {
			$popup = isset( $row['popup_id'] ) && is_scalar( $row['popup_id'] ) && preg_match( '/^\d{1,10}$/', (string) $row['popup_id'] ) ? (int) $row['popup_id'] : 0;
			if ( $popup <= 0 ) {
				return null;
			}
			$out['p'] = $popup;
		}
		if ( 'toggle_class' === $action ) {
			$class = self::text( $row, 'class_name', '' );
			if ( ! self::class_allowed( $class ) ) {
				return null;
			}
			$out['c'] = $class;
		}
		if ( 'set_attribute' === $action ) {
			$name  = self::text( $row, 'attribute', '' );
			$value = isset( $row['attribute_value'] ) && is_scalar( $row['attribute_value'] ) ? trim( (string) $row['attribute_value'] ) : '';
			if ( ! self::attribute_allowed( $name, $value ) ) {
				return null;
			}
			$out['n'] = $name;
			$out['v'] = $value;
		}
		if ( 'load' === $trigger ) {
			$delay = isset( $row['delay'] ) && is_numeric( $row['delay'] ) ? (int) $row['delay'] : 0;
			$delay = max( 0, min( self::MAX_DELAY, $delay ) );
			if ( $delay ) {
				$out['d'] = $delay;
			}
		}
		if ( in_array( $action, self::ANIMATED, true ) ) {
			$animation = self::text( $row, 'animation', '' );
			if ( '' !== $animation && 'none' !== $animation && self::animation_allowed( $animation ) ) {
				$out['m'] = $animation;
			}
		}
		if ( in_array( $trigger, array( 'click', 'hover' ), true ) && 'yes' === self::text( $row, 'once', '' ) ) {
			$out['o'] = 1;
		}
		return $out;
	}

	/**
	 * Filas guardadas → filas compactas válidas, como mucho `MAX_ROWS`.
	 *
	 * @param mixed      $rows    Valor del repetidor.
	 * @param string     $self_id ID del elemento.
	 * @param array|null $ids     IDs del documento (claves) o `null`.
	 */
	public static function rows( $rows, string $self_id, ?array $ids = null ): array {
		$out = array();
		if ( ! is_array( $rows ) || ! preg_match( self::ID_PATTERN, $self_id ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$clean = self::row( $row, $self_id, $ids );
			if ( null !== $clean ) {
				$out[] = $clean;
				if ( count( $out ) >= self::MAX_ROWS ) {
					break;
				}
			}
		}
		return $out;
	}

	public static function class_allowed( string $class ): bool {
		return 1 === preg_match( self::CLASS_PATTERN, $class ) && ! preg_match( self::CLASS_DENIED, $class );
	}

	public static function attribute_allowed( string $name, string $value ): bool {
		return isset( self::ATTRIBUTES[ $name ] ) && 1 === preg_match( self::ATTRIBUTES[ $name ], $value );
	}

	/** Animación de entrada de la lista del núcleo (con las que añada su filtro); sin el núcleo, sólo la forma. */
	public static function animation_allowed( string $name ): bool {
		if ( ! preg_match( self::ANIMATION, $name ) ) {
			return false;
		}
		if ( ! class_exists( '\Elementor\Control_Animation' ) ) {
			return true;
		}
		static $names = null;
		if ( null === $names ) {
			$names = array();
			foreach ( (array) \Elementor\Control_Animation::get_animations() as $group ) {
				foreach ( (array) $group as $key => $label ) {
					$names[ (string) $key ] = true;
				}
			}
		}
		return isset( $names[ $name ] );
	}

	/** ¿Alguna fila necesita los paquetes de PRO? */
	public static function needs_pro( array $rows ): bool {
		foreach ( $rows as $row ) {
			if ( in_array( $row['a'], self::PRO, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** ¿Alguna fila muestra u oculta (y por tanto necesita la regla `[hidden]` en el CSS del documento)? */
	public static function needs_hidden_rule( array $rows ): bool {
		foreach ( $rows as $row ) {
			if ( in_array( $row['a'], self::VISIBILITY, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Descendientes interactivos de un HTML (el contenido de un elemento, sin su envoltorio).
	 *
	 * Cuenta `a` y `button` (con o sin `href`), `select`, `textarea`, `summary`, `iframe`, `input` que no es oculto,
	 * `video`/`audio` con `controls`, y cualquier etiqueta con `tabindex` no negativo o `contenteditable`. El contenido
	 * de `script`, `style`, `template`, `noscript` y `textarea` y los comentarios no cuentan.
	 *
	 * @return array{count:int,tag:string,offset:int,attrs:string} `tag`, `offset` y `attrs` describen el único
	 *                                                              descendiente cuando `count` es 1.
	 */
	public static function analyze( string $html ): array {
		$clean = preg_replace_callback(
			'~(<(script|style|template|noscript|textarea)\b[^>]*>)(.*?)(</\2\s*>)~is',
			function ( $m ) {
				return $m[1] . str_repeat( ' ', strlen( $m[3] ) ) . $m[4];
			},
			$html
		);
		$clean = preg_replace_callback(
			'~<!--.*?-->~s',
			function ( $m ) {
				return str_repeat( ' ', strlen( $m[0] ) );
			},
			(string) $clean
		);
		$out = array(
			'count'  => 0,
			'tag'    => '',
			'offset' => -1,
			'attrs'  => '',
		);
		if ( ! preg_match_all( '~<([a-zA-Z][a-zA-Z0-9-]*)((?:\s[^>]*)?)>~', (string) $clean, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $out;
		}
		foreach ( $tags as $tag ) {
			$name  = strtolower( $tag[1][0] );
			$attrs = (string) $tag[2][0];
			if ( ! self::interactive( $name, $attrs ) ) {
				continue;
			}
			$out['count']++;
			if ( 1 === $out['count'] ) {
				$out['tag']    = $name;
				$out['offset'] = (int) $tag[0][1];
				$out['attrs']  = $attrs;
			}
		}
		return $out;
	}

	/** ¿Es interactiva esta etiqueta? */
	public static function interactive( string $name, string $attrs ): bool {
		if ( in_array( $name, self::ALWAYS_INTERACTIVE, true ) ) {
			return true;
		}
		if ( 'input' === $name ) {
			return ! preg_match( '/\stype\s*=\s*["\']?hidden\b/i', $attrs );
		}
		if ( ( 'video' === $name || 'audio' === $name ) && preg_match( '/\scontrols\b/i', $attrs ) ) {
			return true;
		}
		if ( preg_match( '/\stabindex\s*=\s*["\']?\s*(-?)\d/i', $attrs, $m ) && '-' !== $m[1] ) {
			return true;
		}
		return (bool) preg_match( '/\scontenteditable(?!\s*=\s*["\']?false\b)(?=[\s=>\/]|$)/i', $attrs );
	}

	private static function text( array $row, string $key, string $default ): string {
		if ( ! isset( $row[ $key ] ) || ! is_scalar( $row[ $key ] ) ) {
			return $default;
		}
		$value = trim( (string) $row[ $key ] );
		return '' === $value ? $default : $value;
	}
}
