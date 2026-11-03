<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * Lo que la conversión de un elemento escribe (F8, V.9): ajustes nuevos con la regla «no pisar», trozos de
 * `custom_css` validados, hijos nuevos y anotaciones del informe.
 *
 * - **No pisar:** una extensión nunca sobrescribe una clave de destino que ya tenga otro valor (`_transform_*`,
 *   `motion_fx_*`, `sticky*`, `_animation`…). Si una de sus claves choca, no escribe ninguna (todo o nada por
 *   extensión) y anota `ext.conflict` («Pendiente de revisar»). `custom_css` siempre se añade, nunca se sustituye.
 * - **CSS:** cada trozo pasa `Css_Value::block()`; uno que no la pase se descarta con `ext.css_rejected`.
 */
final class Out {
	/** @var array ajustes que se escriben (parten de los originales). */
	public $s;
	/** @var array ajustes originales, con los valores v4 tal cual. */
	public $orig;
	/** @var array el elemento (tipo, widget, ID e hijos). */
	public $element;
	/** @var int documento. */
	public $post_id;
	/** @var string[] trozos de CSS para `custom_css`. */
	public $css = array();
	/** @var array<int, array{0:string,1:array}> anotaciones: [código, detalle]. */
	public $items = array();
	/** @var array hijos que se añaden al principio del elemento. */
	public $prepend = array();
	/** @var string extensión en curso. */
	public $slug = '';

	public function __construct( array $element, int $post_id ) {
		$this->element = $element;
		$this->orig    = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$this->s       = $this->orig;
		$this->post_id = $post_id;
	}

	/** Tipo de elemento (`widget`, `section`, `column`, `container`…). */
	public function el_type(): string {
		return (string) ( $this->element['elType'] ?? 'widget' );
	}

	/** Tipo de widget efectivo: el destino de su mapa si es un widget heredado que la migración traduce. */
	public function widget(): string {
		return Dispatcher::effective_widget( (string) ( $this->element['widgetType'] ?? '' ) );
	}

	/** ID del elemento, sólo `[a-z0-9]` (como lo genera el editor). */
	public function id(): string {
		$id = (string) ( $this->element['id'] ?? '' );
		return preg_match( '/^[a-zA-Z0-9]{1,20}$/', $id ) ? $id : '';
	}

	/** «heading #abc123» para el informe. */
	public function label(): string {
		$type = 'widget' === $this->el_type() ? (string) ( $this->element['widgetType'] ?? 'widget' ) : $this->el_type();
		return trim( $type . ( '' !== $this->id() ? ' #' . $this->id() : '' ) );
	}

	/** Valor de un ajuste original (desenvuelto) o el valor por defecto de la extensión en curso. */
	public function get( string $key ) {
		return Values::get( $this->orig, $key, Dispatcher::default_of( $this->slug, $key ) );
	}

	/** ¿Está vacío un valor? (texto vacío, nulo, lista vacía o deslizador sin tamaño). */
	public static function blank( $value ): bool {
		if ( null === $value || '' === $value || array() === $value ) {
			return true;
		}
		if ( is_array( $value ) && array_key_exists( 'url', $value ) && ! array_key_exists( 'size', $value ) ) {
			return '' === trim( (string) $value['url'] );
		}
		return is_array( $value ) && array_key_exists( 'size', $value ) && ( '' === $value['size'] || null === $value['size'] ) && ! isset( $value['sizes'] );
	}

	/**
	 * Escribe varias claves de una vez con la regla «no pisar»: si alguna ya tiene otro valor, no escribe ninguna,
	 * anota `ext.conflict` y devuelve false.
	 */
	public function set_all( array $values ): bool {
		foreach ( $values as $key => $value ) {
			if ( array_key_exists( $key, $this->s ) && ! self::blank( $this->s[ $key ] ) && $this->s[ $key ] != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons -- 0 y '0' son el mismo valor guardado.
				$this->note( 'ext.conflict', array( 'type' => Dispatcher::label( $this->slug ), 'name' => (string) $key ) );
				return false;
			}
		}
		foreach ( $values as $key => $value ) {
			$this->s[ $key ] = $value;
		}
		return true;
	}

	/** Lleva una referencia global o dinámica de una clave de origen a una de destino. */
	public function carry( string $from, string $to ): void {
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			if ( isset( $this->orig[ $bag ][ $from ] ) && is_string( $this->orig[ $bag ][ $from ] ) && ! isset( $this->s[ $bag ][ $to ] ) ) {
				$this->s[ $bag ]        = is_array( $this->s[ $bag ] ?? null ) ? $this->s[ $bag ] : array();
				$this->s[ $bag ][ $to ] = $this->orig[ $bag ][ $from ];
			}
		}
	}

	/** Añade un trozo de CSS validado para `custom_css`. */
	public function css( string $css ): void {
		$css = trim( $css );
		if ( '' === $css ) {
			return;
		}
		if ( ! Css_Value::block( $css ) ) {
			$this->note( 'ext.css_rejected', array( 'type' => Dispatcher::label( $this->slug ) ) );
			return;
		}
		$this->css[] = $css;
	}

	/** Anotación del informe. El elemento va siempre en el detalle. */
	public function note( string $code, array $detail = array() ): void {
		$this->items[] = array( $code, array( 'element' => $this->label() ) + $detail );
	}

	/** Pérdida declarada de la extensión en curso (información). */
	public function loss( string $what ): void {
		$this->note( 'ext.loss.' . Dispatcher::code( $this->slug ), array( 'note' => $what ) );
	}

	/* ---------------------------------------------------------------- CSS */

	/** Regla `selector{a:b;c:d}` con las declaraciones válidas (vacía sin ninguna). */
	public static function rule( string $selector, array $decls ): string {
		$decls = array_values( array_filter( array_map( 'strval', $decls ), static function ( $d ) {
			return '' !== trim( $d );
		} ) );
		return $decls ? $selector . '{' . implode( ';', $decls ) . '}' : '';
	}

	/**
	 * Reglas por dispositivo: `'' => declaraciones` del escritorio y `dispositivo => declaraciones` dentro de su `@media`,
	 * en el orden de los puntos de corte.
	 */
	public static function responsive( string $selector, array $by_device ): string {
		$css = isset( $by_device[''] ) ? self::rule( $selector, (array) $by_device[''] ) : '';
		foreach ( array_keys( Values::breakpoints() ) as $device ) {
			if ( ! empty( $by_device[ $device ] ) ) {
				$rule = self::rule( $selector, (array) $by_device[ $device ] );
				$css .= '' !== $rule ? Values::media( $device ) . '{' . $rule . '}' : '';
			}
		}
		return $css;
	}

	/**
	 * Declaraciones por dispositivo de un control responsivo: `$format( valor ) → declaración|null` para cada valor
	 * guardado (y el valor por defecto en el escritorio).
	 */
	public function by_device( string $key, callable $format, array $into = array() ): array {
		foreach ( Values::responsive( $this->orig, $key, Dispatcher::default_of( $this->slug, $key ) ) as $device => $value ) {
			$decl = $format( $value );
			if ( null !== $decl && '' !== $decl ) {
				$into[ $device ][] = $decl;
			}
		}
		return $into;
	}

	/**
	 * Selector del contenedor interno de un widget: con el marcado optimizado del núcleo el widget no tiene
	 * `.elementor-widget-container` (`common-optimized.php:10-16`) y el destino es el envoltorio. Si no se sabe, los
	 * dos con `:is()` (C1-21).
	 */
	public function inner( string $state = '' ): string {
		$wrapper = Dispatcher::inner_wrapper( $this->widget() );
		if ( true === $wrapper ) {
			return 'selector' . $state . ' > .elementor-widget-container';
		}
		if ( false === $wrapper ) {
			return 'selector' . $state;
		}
		return ':is(selector' . $state . ' > .elementor-widget-container, selector' . $state . ':not(:has(> .elementor-widget-container)))';
	}
}
