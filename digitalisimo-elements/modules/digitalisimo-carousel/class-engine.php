<?php
namespace Digitalisimo\Elements\Carousel;

defined( 'ABSPATH' ) || exit;

/**
 * Motor de carrusel `digi-crs` (C1, `docs/pro/c1.md` §1): marcado del servidor. El editor pinta el mismo marcado (los
 * widgets se pintan en el servidor), y `assets/js/carousel.js` le da vida.
 *
 * ```
 * section.digi-crs[.mods] aria-roledescription="carrusel" aria-label data-digi-* style="--digi-crs-speed:500ms"
 *   button.digi-crs__pause (sólo con autoplay; `hidden` hasta que el script arranca)
 *   button.digi-crs__prev  (sólo con flechas)
 *   div.digi-crs__pagination data-digi-dot (sólo con paginación) > button.digi-crs__dot × N | span.digi-crs__fraction
 *     > span.digi-crs__now + span.digi-crs__total | span.digi-crs__bar > span.digi-crs__fill
 *   button.digi-crs__next
 *   div.digi-crs__track [dir] aria-live > .digi-crs__slide × N (role="group", aria-roledescription, aria-label «1 de N»)
 * ```
 *
 * Con una sola diapositiva no se imprimen flechas, paginación ni pausa (como PRO). Los controles van antes de la pista:
 * orden de foco, y un `querySelector( ':scope>…' )` desde la raíz nunca encuentra los de un carrusel anidado. El motor
 * trata como diapositiva cada hijo directo de `.digi-crs__track` (en el carrusel anidado, los contenedores `.e-con`,
 * con `slide_attribute_array()`).
 *
 * **Atributos de la raíz** (los escribe `open()`; el script los lee una vez): `data-digi-autoplay` (ms; ausente sin
 * reproducción automática), `data-digi-loop`, `data-digi-hover` (pausa con el puntero encima), `data-digi-interaction`
 * (se para tras una acción del usuario), `data-digi-effect` (`slide`|`fade`), `data-digi-center`,
 * `data-digi-pagination` (`bullets`|`fraction`|`progressbar`), `data-digi-sync` (`#id` del carrusel principal, en el de
 * miniaturas). `--digi-crs-speed` va en el `style` de la raíz.
 *
 * **Clases de estado que pone el script:** `digi-crs--ready` (cuando el carrusel quedó iniciado: el arnés A/B espera a que
 * no quede ningún `.digi-crs:not(.digi-crs--ready)`), `--paused`, `--locked` (todo cabe en una página: se ocultan
 * flechas, paginación y pausa, como `watchOverflow`), `--anim` (fundido tras el primer cambio), `--moving` (desplazamiento
 * programado en curso). En las diapositivas: `digi-crs__current` (la actual), `digi-crs__synced` (miniatura de la
 * diapositiva actual del carrusel principal) e `inert` (fuera de la ventana visible).
 *
 * **Modificadores** (args o `classes`): `digi-crs--fade`, `digi-crs--center`, `digi-crs--arrows-out` (pista 60 px más
 * estrecha y flechas en los bordes, como `arrows_position = outside`), `digi-crs--pag-out` (reserva
 * `--digi-crs-pag-space` debajo de la pista para la paginación, como `pagination_position = outside`).
 *
 * **Variables** (cada widget las fija con `selectors`, por dispositivo, normalmente en `{{WRAPPER}}`; la hoja nunca las
 * declara en `.digi-crs`, sólo las lee con su valor por defecto, y las anula en cada `.digi-crs__slide` para que un
 * carrusel dentro de una diapositiva no herede las del de fuera). Entre paréntesis, el valor por defecto, que reproduce
 * los carruseles de PRO (`e-swiper.css` del núcleo y las hojas de PRO):
 * - Pista: `--digi-crs-view` (1, diapositivas visibles, entero), `--digi-crs-scroll` (1, diapositivas por paso),
 *   `--digi-crs-gap` (0px), `--digi-crs-offset-start` / `--digi-crs-offset-end` (0px, asomo lateral),
 *   `--digi-crs-height` (auto, alto de la pista), `--digi-crs-width` (100%, ancho de la pista, centrada),
 *   `--digi-crs-align` (stretch, `align-items` de la pista), `--digi-crs-speed` (500ms).
 * - Flechas: `--digi-crs-arrow-size` (25px, tamaño del icono), `--digi-crs-arrow-color` (hsla(0,0%,93%,.9)),
 *   `--digi-crs-arrow-hover` (el color normal), `--digi-crs-focus` (currentColor, contorno del foco).
 *   Posición de la anterior (la siguiente igual con `next` y medida desde el borde final): `--digi-crs-prev-ax` (0%,
 *   alineación horizontal) + `--digi-crs-prev-px` (10px, desplazamiento) → `inset-inline-start`; `--digi-crs-prev-ay`
 *   (50%) + `--digi-crs-prev-py` (0px) − `--digi-crs-prev-cs` (la mitad de `--digi-crs-pag-space` con `--pag-out`, si
 *   no 0px) → `top`; `--digi-crs-prev-tx` (0px) y `--digi-crs-prev-ty` (-50%) → `translate`.
 * - Paginación: `--digi-crs-pag-space` (30px, reserva con `--pag-out`); posición `--digi-crs-pag-x` (50%,
 *   `inset-inline-start`), `--digi-crs-pag-y` (calc(100% - 5px), `top`), `--digi-crs-pag-tx` (-50%), `--digi-crs-pag-ty`
 *   (-100%).
 * - Puntos (botones de 24 × 24 px como mínimo con el punto dibujado abajo y centrado): `--digi-crs-dot-size` (6px),
 *   `--digi-crs-dot-gap` (6px, a cada lado: entre centros, máx(24px, tamaño + 2 × separación)), `--digi-crs-dot-color`
 *   (#000) con `--digi-crs-dot-opacity` (.2), `--digi-crs-dot-hover` (el color normal) con
 *   `--digi-crs-dot-hover-opacity` (la normal), `--digi-crs-dot-active` (#000, opacidad 1).
 * - Fracción: `--digi-crs-fraction-color` (el color heredado; si no, `--digi-crs-dot-active` no la toca).
 * - Barra: `--digi-crs-bar-size` (4px), `--digi-crs-bar-bg` (rgba(0,0,0,.25)), `--digi-crs-bar-color`
 *   (`--digi-crs-dot-active`), `--digi-crs-bar-hover` (el color de la barra); va arriba de la pista, como en PRO.
 *
 * Selectores: `scope()` devuelve el de la raíz del propio widget (sin alcanzar un carrusel anidado en una diapositiva),
 * para las reglas que no son variables.
 */
final class Engine {
	const HANDLE = 'digitalisimo-carousel';

	const EFFECTS     = array( 'slide', 'fade' );
	const PAGINATIONS = array( 'bullets', 'fraction', 'progressbar' );

	/** Iconos propios por defecto: los mismos trazos que el chevron de la biblioteca de iconos del núcleo. */
	const ICON_PREV  = '<svg class="digi-crs__icon" viewBox="0 0 1000 1000" aria-hidden="true" focusable="false"><path d="M646 125C629 125 613 133 604 142L308 442C296 454 292 471 292 487 292 504 296 521 308 533L604 854C617 867 629 875 646 875 663 875 679 871 692 858 704 846 713 829 713 812 713 796 708 779 692 767L438 487 692 225C700 217 708 204 708 187 708 171 704 154 692 142 675 129 663 125 646 125Z"/></svg>';
	const ICON_NEXT  = '<svg class="digi-crs__icon" viewBox="0 0 1000 1000" aria-hidden="true" focusable="false"><path d="M696 533C708 521 713 504 713 487 713 471 708 454 696 446L400 146C388 133 375 125 354 125 338 125 325 129 313 142 300 154 292 171 292 187 292 204 296 221 308 233L563 492 304 771C292 783 288 800 288 817 288 833 296 850 308 863 321 871 338 875 354 875 371 875 388 867 400 854L696 533Z"/></svg>';
	const ICON_PAUSE = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path class="digi-crs__i-pause" d="M7 5h4v14H7zm6 0h4v14h-4z"/><path class="digi-crs__i-play" d="M8 5v14l11-7z"/></svg>';

	/** Valores por defecto de `open()`. */
	const DEFAULTS = array(
		'label'               => '',
		'count'               => 0,
		'autoplay'            => 0,
		'loop'                => false,
		'hover'               => false,
		'interaction'         => false,
		'effect'              => 'slide',
		'speed'               => 500,
		'center'              => false,
		'pagination'          => '',
		'pagination_position' => 'inside',
		'arrows'              => false,
		'arrows_position'     => 'inside',
		'prev'                => null,
		'next'                => null,
		'dir'                 => '',
		'classes'             => array(),
		'attributes'          => array(),
		'id'                  => '',
		'sync'                => '',
	);

	/**
	 * Todo hasta abrir la pista (`div.digi-crs__track`), incluido.
	 *
	 * @param array $args `label` (nombre accesible), `count` (diapositivas), `autoplay` (ms, 0 sin), `loop`, `hover`,
	 *                    `interaction`, `effect` (`slide`|`fade`), `speed` (ms), `center`, `pagination` (''|`bullets`|
	 *                    `fraction`|`progressbar`), `pagination_position` (`inside`|`outside`), `arrows`,
	 *                    `arrows_position` (`inside`|`outside`), `prev` y `next` (HTML del icono, ya seguro, o `null` para
	 *                    el propio), `dir` (''|`ltr`|`rtl`, en la pista), `classes` (array|string, en la raíz),
	 *                    `attributes` (nombre => valor, en la raíz), `id`, `sync` (`#id` del carrusel principal).
	 */
	public static function open( array $args ): string {
		$a     = array_merge( self::DEFAULTS, $args );
		$count = max( 0, (int) $a['count'] );
		$many  = $count > 1;
		$delay = max( 0, (int) $a['autoplay'] );
		$fx    = in_array( $a['effect'], self::EFFECTS, true ) ? $a['effect'] : 'slide';
		$pag   = in_array( $a['pagination'], self::PAGINATIONS, true ) ? $a['pagination'] : '';
		$label = trim( (string) $a['label'] );

		$classes = array( 'digi-crs' );
		if ( 'fade' === $fx ) {
			$classes[] = 'digi-crs--fade';
		}
		if ( $a['center'] ) {
			$classes[] = 'digi-crs--center';
		}
		if ( $a['arrows'] && 'outside' === $a['arrows_position'] ) {
			$classes[] = 'digi-crs--arrows-out';
		}
		if ( $pag && 'outside' === $a['pagination_position'] ) {
			$classes[] = 'digi-crs--pag-out';
		}
		$extra   = is_array( $a['classes'] ) ? $a['classes'] : preg_split( '/\s+/', (string) $a['classes'] );
		$classes = array_merge( $classes, array_filter( array_map( 'strval', (array) $extra ) ) );

		$attrs = array(
			'class'                => implode( ' ', array_unique( $classes ) ),
			'aria-roledescription' => __( 'carrusel', 'pro-elements' ),
			'aria-label'           => '' !== $label ? $label : __( 'Carrusel', 'pro-elements' ),
		);
		if ( '' !== (string) $a['id'] ) {
			$attrs['id'] = (string) $a['id'];
		}
		if ( $delay ) {
			$attrs['data-digi-autoplay'] = (string) $delay;
			if ( $a['hover'] ) {
				$attrs['data-digi-hover'] = '1';
			}
			if ( $a['interaction'] ) {
				$attrs['data-digi-interaction'] = '1';
			}
		}
		if ( $a['loop'] ) {
			$attrs['data-digi-loop'] = '1';
		}
		$attrs['data-digi-effect'] = $fx;
		if ( $a['center'] ) {
			$attrs['data-digi-center'] = '1';
		}
		if ( $pag ) {
			$attrs['data-digi-pagination'] = $pag;
		}
		if ( '' !== (string) $a['sync'] ) {
			$attrs['data-digi-sync'] = (string) $a['sync'];
		}
		$attrs['style'] = '--digi-crs-speed:' . max( 0, (int) $a['speed'] ) . 'ms';
		foreach ( (array) $a['attributes'] as $name => $value ) {
			if ( is_string( $name ) && preg_match( '/^[a-z][a-z0-9_:.-]*$/', $name ) && ! isset( $attrs[ $name ] ) ) {
				$attrs[ $name ] = (string) $value;
			}
		}

		$html = '<section ' . self::attributes( $attrs ) . '>';
		if ( $many && $delay ) {
			$pause = __( 'Pausar', 'pro-elements' );
			$html .= '<button type="button" class="digi-crs__pause" data-digi-pause="' . esc_attr( $pause ) . '" data-digi-play="' . esc_attr__( 'Reanudar', 'pro-elements' ) . '" aria-label="' . esc_attr( $pause ) . '" hidden>' . self::ICON_PAUSE . '</button>';
		}
		if ( $many && $a['arrows'] ) {
			$html .= '<button type="button" class="digi-crs__prev" aria-label="' . esc_attr__( 'Diapositiva anterior', 'pro-elements' ) . '">' . ( null === $a['prev'] ? self::ICON_PREV : (string) $a['prev'] ) . '</button>';
		}
		if ( $many && $pag ) {
			/* translators: %d: número de la diapositiva. */
			$dot   = __( 'Ir a la diapositiva %d', 'pro-elements' );
			$html .= '<div class="digi-crs__pagination" data-digi-dot="' . esc_attr( $dot ) . '">';
			if ( 'bullets' === $pag ) {
				for ( $i = 1; $i <= $count; $i++ ) {
					$html .= '<button type="button" class="digi-crs__dot" aria-label="' . esc_attr( sprintf( $dot, $i ) ) . '"' . ( 1 === $i ? ' aria-current="true"' : '' ) . '></button>';
				}
			} elseif ( 'fraction' === $pag ) {
				$html .= '<span class="digi-crs__fraction"><span class="digi-crs__now">1</span> / <span class="digi-crs__total">' . $count . '</span></span>';
			} else {
				$html .= '<span class="digi-crs__bar" aria-hidden="true"><span class="digi-crs__fill"></span></span>';
			}
			$html .= '</div>';
		}
		if ( $many && $a['arrows'] ) {
			$html .= '<button type="button" class="digi-crs__next" aria-label="' . esc_attr__( 'Diapositiva siguiente', 'pro-elements' ) . '">' . ( null === $a['next'] ? self::ICON_NEXT : (string) $a['next'] ) . '</button>';
		}
		$track = array( 'class' => 'digi-crs__track', 'aria-live' => $delay ? 'off' : 'polite' );
		if ( in_array( $a['dir'], array( 'ltr', 'rtl' ), true ) ) {
			$track['dir'] = $a['dir'];
		}
		return $html . '<div ' . self::attributes( $track ) . '>';
	}

	/** Cierra la pista y la raíz. */
	public static function close(): string {
		return '</div></section>';
	}

	/**
	 * Atributos de una diapositiva como arreglo (para `add_render_attribute()` o un contenedor hijo).
	 *
	 * @param int    $index Índice desde 0.
	 * @param int    $count Total de diapositivas.
	 * @param string $class Clases propias del widget.
	 */
	public static function slide_attribute_array( int $index, int $count, string $class = '' ): array {
		return array(
			'class'                => trim( 'digi-crs__slide ' . $class ),
			'role'                 => 'group',
			'aria-roledescription' => __( 'diapositiva', 'pro-elements' ),
			/* translators: 1: número de la diapositiva, 2: total. */
			'aria-label'           => sprintf( __( '%1$d de %2$d', 'pro-elements' ), $index + 1, max( 1, $count ) ),
		);
	}

	/** Los mismos atributos, como cadena (sin espacio inicial) para `<div …>`. */
	public static function slide_attributes( int $index, int $count, string $class = '' ): string {
		return self::attributes( self::slide_attribute_array( $index, $count, $class ) );
	}

	/**
	 * Selector de la raíz del propio widget (con o sin el envoltorio interior del núcleo), más un sufijo: sin alcanzar un
	 * carrusel anidado dentro de una diapositiva. P. ej. `scope( ' > .digi-crs__track > .digi-crs__slide' )`.
	 */
	public static function scope( string $suffix = '' ): string {
		return '{{WRAPPER}} > .digi-crs' . $suffix . ', {{WRAPPER}} > .elementor-widget-container > .digi-crs' . $suffix;
	}

	/** `nombre="valor"` escapados, separados por espacios. */
	public static function attributes( array $attrs ): string {
		$out = array();
		foreach ( $attrs as $name => $value ) {
			$out[] = $name . '="' . esc_attr( (string) $value ) . '"';
		}
		return implode( ' ', $out );
	}
}
