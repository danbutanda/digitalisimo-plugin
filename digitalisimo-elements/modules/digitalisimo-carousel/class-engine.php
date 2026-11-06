<?php
namespace Digitalisimo\Elements\Carousel;

defined( 'ABSPATH' ) || exit;

/**
 * Motor de carrusel `digi-crs` (C1, `docs/pro/c1.md` §1): marcado del servidor. El editor pinta el mismo marcado (los
 * widgets se pintan en el servidor), y `assets/js/carousel.js` le da vida.
 *
 * ```
 * section.digi-crs[.mods] aria-roledescription="carrusel" aria-label data-digi-* style="--digi-crs-speed:500ms"
 *   button.digi-crs__pause (sólo con autoplay; `hidden` hasta que el script arranca, y oculto a la vista salvo con el
 *     foco del teclado: PRO no tenía botón, y así se ve igual sin perder el mecanismo de WCAG 2.2.2)
 *   button.digi-crs__arrow.digi-crs__prev (sólo con flechas)
 *   div.digi-crs__pagination data-digi-dot (sólo con paginación) > button.digi-crs__dot × N | span.digi-crs__fraction
 *     > span.digi-crs__now + span.digi-crs__total | div.digi-crs__bar > div.digi-crs__fill
 *   button.digi-crs__arrow.digi-crs__next
 *   div.digi-crs__track [dir] aria-live > .digi-crs__slide × N (role="group", aria-roledescription, aria-label «1 de N»)
 * ```
 *
 * Con una sola diapositiva no se imprimen flechas, paginación ni pausa (como PRO). Los controles van antes de la pista:
 * orden de foco, y un `querySelector( ':scope>…' )` desde la raíz nunca encuentra los de un carrusel anidado. El motor
 * trata como diapositiva cada hijo directo de `.digi-crs__track` (en el carrusel anidado, los contenedores `.e-con`,
 * con `slide_attribute_array()`). Sin JavaScript la pista es un desplazamiento horizontal con `scroll-snap`.
 *
 * **Atributos de la raíz** (los escribe `open()`; el script los lee una vez): `data-digi-autoplay` (ms; ausente sin
 * reproducción automática), `data-digi-loop`, `data-digi-hover` (pausa con el puntero encima), `data-digi-interaction`
 * (se para tras una acción del usuario), `data-digi-effect` (`slide`|`fade`), `data-digi-center`,
 * `data-digi-pagination` (`bullets`|`fraction`|`progressbar`), `data-digi-sync` (`#id` del carrusel principal, en el de
 * miniaturas), `data-digi-grab` (cursor de agarre, `grabCursor` de Swiper en PRO), `data-digi-click` (un clic en una
 * diapositiva la lleva a la vista, `slideToClickedSlide`) y `data-digi-long` (con el ratón, un gesto rápido no cambia de
 * diapositiva: `shortSwipes` desactivado del Carrusel anidado de PRO fuera de las pantallas táctiles). `--digi-crs-speed` y `--digi-crs-n` (número de diapositivas) van en el `style` de la raíz; la hoja acota
 * las visibles a `min( --digi-crs-view, --digi-crs-n )`, como el `Math.min` de Swiper en PRO.
 *
 * **Clases de estado que pone el script:** `digi-crs--ready` en la raíz cuando el carrusel quedó iniciado (el arnés A/B
 * espera a que no quede ningún `.digi-crs:not(.digi-crs--ready)`), `--paused`, `--locked` (sin bucle, todo cabe en una
 * página: se ocultan paginación y pausa y las flechas quedan atenuadas con `aria-disabled`, como `watchOverflow` con la
 * hoja del núcleo en PRO; con bucle nunca se bloquea), `--anim` (fundido tras el primer cambio), `--drag` (arrastre con
 * el ratón en curso). En las diapositivas: `digi-crs__current` (la primera visible, o la centrada), `digi-crs__synced` (en
 * las miniaturas, la de la diapositiva actual del carrusel principal), `inert` (fuera de la ventana visible) y, en las
 * copias del bucle, `digi-crs__clone` y `slick-cloned` (la clase que el lightbox del núcleo ya excluye de sus galerías).
 *
 * **Modificadores** (args o `classes`): `digi-crs--fade`, `digi-crs--center` (la actual centrada, con espacio a los
 * lados), `digi-crs--arrows-out` (30 px a cada lado para las flechas, en el borde; `arrows_position = outside`),
 * `digi-crs--pag-out` (reserva `--digi-crs-pag-space` debajo de la pista para la paginación;
 * `pagination_position = outside`).
 *
 * **Variables.** Cada widget las fija con `selectors` (por dispositivo) en `{{WRAPPER}}`, o con su hoja en
 * `.elementor-widget-<nombre>`; la hoja del motor nunca las declara, sólo las lee con su valor por defecto, que reproduce
 * los carruseles de PRO (`e-swiper.css` del núcleo y las hojas de PRO). Como heredan, **cada hoja de widget declara en
 * `.elementor-widget-<nombre>` las de maquetación que use** (al menos `--digi-crs-view` y `--digi-crs-gap`), para que un
 * carrusel dentro de una diapositiva de otro no herede las del de fuera.
 * - Pista: `--digi-crs-view` (1, diapositivas visibles, entero), `--digi-crs-scroll` (1, diapositivas por paso; lo lee
 *   el script), `--digi-crs-gap` (0px), `--digi-crs-offset-start` / `--digi-crs-offset-end` (0px, asomo lateral),
 *   `--digi-crs-height` (auto, alto de la pista), `--digi-crs-width` (100%, ancho de la pista, centrada),
 *   `--digi-crs-align` (stretch, `align-items` de la pista: «misma altura»), `--digi-crs-speed` (500ms).
 *   `--digi-crs-basis` la calcula la pista (ancho de cada diapositiva: `flex-basis`); sólo se lee, p. ej. para dárselo a
 *   hijos con reglas propias más fuertes (los `.e-con` del carrusel anidado).
 * - Flechas: `--digi-crs-arrow-size` (25px, tamaño del icono), `--digi-crs-arrow-color` (rgba(237,237,237,.9)),
 *   `--digi-crs-arrow-hover` (el color normal). Posición: `--digi-crs-prev-x` (`inset-inline-start`; 10px, 0px con
 *   `--arrows-out`), `--digi-crs-prev-y` (`top`; 50%, o el centro de la pista con `--pag-out`), `--digi-crs-prev-t`
 *   (`translate`; `0 -50%`), y las mismas con `next` (`inset-inline-end`). Las compone `Nested_Controls` con sus
 *   piezas (`-ax`, `-px`, `-ay`, `-py`, `-tx`, `-ty`).
 * - Paginación: `--digi-crs-pag-space` (30px, reserva con `--pag-out`); posición `--digi-crs-pag-x`
 *   (`inset-inline-start`; 50%), `--digi-crs-pag-y` (`top`; calc(100% - 5px)) y `--digi-crs-pag-t` (`translate`;
 *   -50% -100%, con `--direction-multiplier` del núcleo en RTL). Con `progressbar`, arriba y a todo el ancho.
 * - Puntos (cada uno un botón transparente con el punto en `::before`, a 7 px del pie de un botón de 24 px de alto como
 *   mínimo, con el centro a 15 px del borde como PRO; en el carrusel anidado y en el de bucle el botón mide lo que el
 *   punto y queda pegado al borde de la paginación): `--digi-crs-dot-size` (6px), `--digi-crs-dot-gap` (6px a cada lado:
 *   el ancho del botón y el paso entre centros son tamaño + 2 × separación, 18 px, como Swiper; el área táctil crece
 *   sólo en vertical, a 24 px, con un `::after` que no cambia el paso), `--digi-crs-dot-color` (#000) con
 *   `--digi-crs-dot-opacity` (.2), `--digi-crs-dot-hover` (el color normal), `--digi-crs-dot-active` (#000, opacidad 1).
 * - Fracción: hereda el color y la tipografía (los controles la pintan con un selector directo).
 * - Barra: `--digi-crs-bar-size` (4px), fondo #00000040, `--digi-crs-bar-color` (`--digi-crs-dot-active`).
 *
 * **No existen** (las nombraba un borrador de esta cabecera; el motor no las lee): `--digi-crs-prev-px|ax|ay|py|cs|tx|ty`,
 * `--digi-crs-next-…`, `--digi-crs-pag-ax|ox|ay|oy|tx|ty` (sólo piezas que componen `Nested_Controls`; para mover las
 * flechas o la paginación se fijan `--digi-crs-prev-x|y|t`, `--digi-crs-next-x|y|t` y `--digi-crs-pag-x|y|t`),
 * `--digi-crs-dot-hover-opacity`, `--digi-crs-fraction-color`, `--digi-crs-bar-bg`, `--digi-crs-bar-hover` y
 * `--digi-crs-focus`. `--digi-crs-fraction-size` la escribe la tipografía de la fracción de `Nested_Controls`, pero el
 * motor no la lee.
 *
 * **Páginas** (como Swiper): sin bucle, 0, paso, 2 × paso… y la última en `count − view` (o `count − 1` centrado); con
 * bucle, una por cada `paso` diapositivas (`ceil(count / paso)`, también en puntos y fracción). **Bucle** (`slide`, más
 * de una diapositiva; como `loopCreate`/`loopFix` de Swiper 8): el script copia visibles + paso diapositivas a cada
 * lado de la pista (`digi-crs__clone`, repitiendo si hay pocas), «siguiente» y la reproducción automática avanzan
 * siempre hacia delante y «anterior» hacia atrás, y antes de cada paso la actual vuelve a su original sin que cambie lo
 * que se ve. Las copias llevan el mismo `inert` por ventana que las originales (una copia a la vista se puede pulsar,
 * como en PRO), y los manejadores del núcleo corren sobre los elementos de las copias en el primer cambio, como PRO en el
 * carrusel anidado y el de bucle. En el editor, el carrusel anidado no copia ni da la vuelta (PRO lo apagaba ahí). Con
 * bucle nunca hay `--locked`. Las visibles se acotan a `--digi-crs-n` (como el `Math.min` de PRO en los carruseles de
 * `carousel/widgets/base.php`); el anidado y el de bucle la fijan a 99 en su hoja, porque el `CarouselBase` del núcleo
 * no acotaba. El desplazamiento lo anima el script con la duración de `--digi-crs-speed` y el imán apagado mientras
 * dura (con la página oculta, sin fotogramas, salta directamente); un desplazamiento sin toque, rueda ni tecla del
 * usuario (un reajuste del navegador) se deshace y nunca cambia de página ni detiene la reproducción automática.
 * **Arrastre con el ratón** (como `simulateTouch` de Swiper, fuera del editor): la pista sigue al puntero y, al soltar,
 * va a la diapositiva más cercana, o a la siguiente o la anterior con un gesto de menos de 300 ms (con `data-digi-long`,
 * un gesto así vuelve a la actual); el clic que sigue a
 * un arrastre no llega a los enlaces ni al lightbox; empezar sobre un campo, un botón o un vídeo no arrastra. La regla
 * de las diapositivas lleva especificidad de id (`:not(#_)`) para ganar a `.e-con.e-flex` del núcleo.
 *
 * Foco visible: contorno de 2 px con 2 px de separación en el color del botón. Selectores: `scope()` devuelve el de la
 * raíz del propio widget (sin alcanzar un carrusel anidado en una diapositiva), para las reglas que no son variables.
 * Los iconos propios de las flechas se invierten en RTL (como PRO, con `is_rtl()`).
 */
final class Engine {
	const HANDLE = 'digitalisimo-carousel';

	const EFFECTS     = array( 'slide', 'fade' );
	const PAGINATIONS = array( 'bullets', 'fraction', 'progressbar' );

	/** Iconos propios por defecto: los mismos trazos que el chevron de la biblioteca de iconos del núcleo. */
	const ICON_PREV  = '<svg class="digi-crs__icon" viewBox="0 0 1000 1000" aria-hidden="true" focusable="false"><path d="M646 125C629 125 613 133 604 142L308 442C296 454 292 471 292 487 292 504 296 521 308 533L604 854C617 867 629 875 646 875 663 875 679 871 692 858 704 846 713 829 713 812 713 796 708 779 692 767L438 487 692 225C700 217 708 204 708 187 708 171 704 154 692 142 675 129 663 125 646 125Z"/></svg>';
	const ICON_NEXT  = '<svg class="digi-crs__icon" viewBox="0 0 1000 1000" aria-hidden="true" focusable="false"><path d="M696 533C708 521 713 504 713 487 713 471 708 454 696 446L400 146C388 133 375 125 354 125 338 125 325 129 313 142 300 154 292 171 292 187 292 204 296 221 308 233L563 492 304 771C292 783 288 800 288 817 288 833 296 850 308 863 321 871 338 875 354 875 371 875 388 867 400 854L696 533Z"/></svg>';
	const ICON_PAUSE = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 5h4v14H7zm6 0h4v14h-4z"/></svg>';

	/** Trazos de los iconos Pausar y Reanudar del botón de pausa (el script cambia el `d` del trazo; `PATH_PLAY` está repetido en `carousel.js`). */
	const PATH_PAUSE = 'M7 5h4v14H7zm6 0h4v14h-4z';
	const PATH_PLAY  = 'M8 5v14l11-7z';

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
		'grab'                => false,
		'click'               => false,
		'long'                => false,
	);

	/**
	 * Todo hasta abrir la pista (`div.digi-crs__track`), incluido.
	 *
	 * @param array $args `label` (nombre accesible), `count` (diapositivas), `autoplay` (ms, 0 sin), `loop`, `hover`,
	 *                    `interaction`, `effect` (`slide`|`fade`), `speed` (ms), `center`, `pagination` (''|`bullets`|
	 *                    `fraction`|`progressbar`), `pagination_position` (`inside`|`outside`), `arrows`,
	 *                    `arrows_position` (`inside`|`outside`), `prev` y `next` (HTML del icono, ya seguro, o `null` para
	 *                    el propio), `dir` (''|`ltr`|`rtl`, en la pista), `classes` (array|string, en la raíz),
	 *                    `attributes` (nombre => valor, en la raíz; nunca `on*` ni los propios), `id`, `sync` (`#id` del carrusel principal),
	 *                    `grab` (cursor de agarre), `click` (un clic en una diapositiva la lleva a la vista) y `long` (con el
	 *                    ratón sólo cuentan los arrastres largos, como `shortSwipes` desactivado).
	 */
	public static function open( array $args ): string {
		$a     = array_merge( self::DEFAULTS, $args );
		$count = max( 0, (int) $a['count'] );
		$many  = $count > 1;
		$delay = max( 0, (int) $a['autoplay'] );
		$fx    = in_array( $a['effect'], self::EFFECTS, true ) ? $a['effect'] : 'slide';
		$pag   = in_array( $a['pagination'], self::PAGINATIONS, true ) ? $a['pagination'] : '';
		$label = trim( (string) $a['label'] );
		$rtl   = function_exists( 'is_rtl' ) && is_rtl();

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
		if ( $a['grab'] ) {
			$attrs['data-digi-grab'] = '1';
		}
		if ( $a['click'] ) {
			$attrs['data-digi-click'] = '1';
		}
		if ( $a['long'] ) {
			$attrs['data-digi-long'] = '1';
		}
		$attrs['style'] = '--digi-crs-speed:' . max( 0, (int) $a['speed'] ) . 'ms;--digi-crs-n:' . $count;
		foreach ( (array) $a['attributes'] as $name => $value ) {
			if ( is_string( $name ) && preg_match( '/^[a-z][a-z0-9_:.-]*$/', $name ) && 0 !== strpos( $name, 'on' ) && ! isset( $attrs[ $name ] ) ) {
				$attrs[ $name ] = (string) $value;
			}
		}

		$html = '<section ' . self::attributes( $attrs ) . '>';
		if ( $many && $delay ) {
			$pause = __( 'Pausar', 'pro-elements' );
			$html .= '<button type="button" class="digi-crs__pause" data-digi-pause="' . esc_attr( $pause ) . '" data-digi-play="' . esc_attr__( 'Reanudar', 'pro-elements' ) . '" aria-label="' . esc_attr( $pause ) . '" hidden>' . self::ICON_PAUSE . '</button>';
		}
		if ( $many && $a['arrows'] ) {
			$html .= '<button type="button" class="digi-crs__arrow digi-crs__prev" aria-label="' . esc_attr__( 'Diapositiva anterior', 'pro-elements' ) . '">' . ( null === $a['prev'] ? ( $rtl ? self::ICON_NEXT : self::ICON_PREV ) : (string) $a['prev'] ) . '</button>';
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
				$html .= '<div class="digi-crs__bar" aria-hidden="true"><div class="digi-crs__fill"></div></div>';
			}
			$html .= '</div>';
		}
		if ( $many && $a['arrows'] ) {
			$html .= '<button type="button" class="digi-crs__arrow digi-crs__next" aria-label="' . esc_attr__( 'Diapositiva siguiente', 'pro-elements' ) . '">' . ( null === $a['next'] ? ( $rtl ? self::ICON_PREV : self::ICON_NEXT ) : (string) $a['next'] ) . '</button>';
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
