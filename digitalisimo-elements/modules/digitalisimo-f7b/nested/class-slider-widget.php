<?php
namespace Digitalisimo\Elements\F7b\Nested;

use Elementor\Controls_Manager;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Slider anidado ligero (`digitalisimo-nested-slider`, F7b, C5; V.7 de la especificación).
 *
 * Cada diapositiva es un contenedor hijo (`Widget_Nested_Base`, como `nested-tabs` del núcleo). Convive con el
 * «Carrusel» anidado de PRO (`nested-carousel`): éste existe porque no carga librerías de carrusel ni su hoja, y por eso
 * su título, su descripción y sus palabras clave son distintos en el panel.
 *
 * - **Primero CSS** (`nested-slider.css`): con `slide`, la pista es un desplazamiento horizontal con `scroll-snap`
 *   (táctil y legible sin JavaScript, sin arrastre propio) y las diapositivas se estiran a la más alta; con `fade` y
 *   `scale`, en cuanto el script arranca, se apilan con `grid-area: 1/1` y la altura es la de la más alta. Sin JavaScript
 *   las tres son la misma pista desplazable. Ninguna altura cambia entre pases.
 * - **Patrón de carrusel** (WCAG 2.2 AA): `section` con `aria-roledescription` y `aria-label`; con reproducción
 *   automática, un botón «Pausar»/«Reanudar» primero en el orden de foco; puntos como botones «Ir a la diapositiva N»
 *   con `aria-current="true"`; región viva `off` con reproducción automática y `polite` sin ella; botones de 24 × 24 px
 *   como mínimo. La navegación es un `select` sin «ninguna»: nunca se quitan a la vez flechas y puntos.
 * - **Marcado** (servidor y plantilla del editor, idénticos): `section.digi-nslider` > [pausa], [anterior],
 *   `div.digi-nslider__dots` (siempre presente: es el destino de los elementos del repetidor en el editor), [siguiente],
 *   `div.digi-nslider__track` > contenedores hijos con `role="group"`, `aria-roledescription` y `aria-label="N de M"`.
 *   Los controles van antes que la pista: así el foco llega a ellos primero y un `querySelector` desde el widget nunca
 *   encuentra antes los de un slider anidado dentro de una diapositiva.
 *
 * El script (`nested-slider.js`) sólo mueve botones, reproducción automática y estado; `inert` en las diapositivas que
 * no se ven lo pone él, para que sin JavaScript todo el contenido siga alcanzable.
 */
class Slider_Widget extends Widget_Nested_Base {
	const NAME = 'digitalisimo-nested-slider';

	/** Hoja y script del front (los registra `Module::register_assets()`). */
	const HANDLE = 'digitalisimo-nested-slider';

	/**
	 * Hoja y script del contador opcional (F8, F8-D14; V.10.4): los registra `Module::register_assets()` y los encola el
	 * cargador del núcleo sólo en las páginas que activan `counter` (declaración `assets` del control).
	 */
	const COUNTER = 'digitalisimo-nested-slider-counter';

	const EFFECTS = array( 'slide', 'fade', 'scale' );

	/** Sin «ninguna»: siempre queda una forma de cambiar de diapositiva sin arrastrar (WCAG 2.1.1 y 2.5.7). */
	const NAVIGATION = array( 'both', 'arrows', 'dots' );

	const POSITIONS = array( 'bar', 'sides' );

	/** Reproducción automática, en milisegundos. */
	const DELAY_MIN     = 2000;
	const DELAY_MAX     = 20000;
	const DELAY_DEFAULT = 5000;

	/** Iconos propios en línea (sin biblioteca de iconos): flechas y pausa/reproducir. */
	const ICON_PREV  = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15.4 5.4 14 4l-8 8 8 8 1.4-1.4L8.8 12z"/></svg>';
	const ICON_NEXT  = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8.6 18.6 10 20l8-8-8-8-1.4 1.4 6.6 6.6z"/></svg>';
	const ICON_PAUSE = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path class="digi-nslider__i-pause" d="M7 5h4v14H7zm6 0h4v14h-4z"/><path class="digi-nslider__i-play" d="M8 5v14l11-7z"/></svg>';

	public function get_name() {
		return self::NAME;
	}

	public function get_title() {
		return esc_html__( 'Slider anidado ligero', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-slider-push';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'slider', 'diapositivas', 'anidado', 'contenedores', 'ligero', 'deslizar', 'fundido', 'presentación', 'digitalisimo' );
	}

	public function get_style_depends(): array {
		return array( 'digitalisimo-nested-slider' );
	}

	public function get_script_depends(): array {
		return array( 'digitalisimo-nested-slider' );
	}

	/** Como los anidados del núcleo: sin envoltorio interior con el marcado optimizado. */
	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	/** Título de una diapositiva nueva (`%d` = número). */
	protected function get_default_children_title() {
		/* translators: %d: número de la diapositiva. */
		return esc_html__( 'Diapositiva n.º %d', 'pro-elements' );
	}

	protected function slide_container( int $index ): array {
		return array(
			'elType'   => 'container',
			'settings' => array(
				'_title'        => sprintf( $this->get_default_children_title(), $index ),
				'content_width' => 'full',
			),
		);
	}

	protected function get_default_children_elements() {
		return array( $this->slide_container( 1 ), $this->slide_container( 2 ), $this->slide_container( 3 ) );
	}

	protected function get_default_repeater_title_setting_key() {
		return 'slide_title';
	}

	/** Los contenedores hijos van directamente en la pista. */
	protected function get_default_children_placeholder_selector() {
		return '.digi-nslider__track';
	}

	/**
	 * Repetidor mejorado del núcleo (como `nested-tabs`): al añadir o quitar una diapositiva, el editor inserta o quita
	 * sólo su punto en `.digi-nslider__dots` con `content_template_single_repeater_item()` y el contenedor en la pista.
	 */
	protected function get_initial_config(): array {
		return array_merge(
			parent::get_initial_config(),
			array(
				'support_improved_repeaters' => true,
				'target_container'           => array( '.digi-nslider__dots' ),
				'node'                       => 'button',
			)
		);
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_slides', array( 'label' => esc_html__( 'Diapositivas', 'pro-elements' ) ) );

		$this->add_control(
			'slider_about',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Slider ligero: cada diapositiva es un contenedor. Desliza con el desplazamiento del navegador (también táctil y sin JavaScript) o funde, sin cargar librerías de carrusel. Para ver varias diapositivas a la vez, usa «Carrusel».', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'slide_title',
			array(
				'label'          => esc_html__( 'Título', 'pro-elements' ),
				'description'    => esc_html__( 'Sólo para el editor: no se muestra en la página.', 'pro-elements' ),
				'type'           => Controls_Manager::TEXT,
				'default'        => esc_html__( 'Diapositiva', 'pro-elements' ),
				'label_block'    => true,
				'style_transfer' => false,
			)
		);

		$defaults = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$defaults[] = array( 'slide_title' => sprintf( $this->get_default_children_title(), $i ) );
		}
		$this->add_control(
			'slides',
			array(
				'label'          => esc_html__( 'Diapositivas', 'pro-elements' ),
				'type'           => Control_Nested_Repeater::CONTROL_TYPE,
				'fields'         => $repeater->get_controls(),
				'default'        => $defaults,
				'title_field'    => '{{{ slide_title }}}',
				'button_text'    => esc_html__( 'Añadir diapositiva', 'pro-elements' ),
				'style_transfer' => false,
			)
		);

		$this->add_control(
			'carousel_label',
			array(
				'label'          => esc_html__( 'Nombre accesible', 'pro-elements' ),
				'description'    => esc_html__( 'Lo anuncian los lectores de pantalla junto con «carrusel». Descríbelo en pocas palabras.', 'pro-elements' ),
				'type'           => Controls_Manager::TEXT,
				'default'        => self::default_label(),
				'label_block'    => true,
				'separator'      => 'before',
				'style_transfer' => false,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section( 'section_options', array( 'label' => esc_html__( 'Ajustes', 'pro-elements' ) ) );

		$this->add_control(
			'effect',
			array(
				'label'          => esc_html__( 'Transición', 'pro-elements' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => 'slide',
				'options'        => array(
					'slide' => esc_html__( 'Deslizar', 'pro-elements' ),
					'fade'  => esc_html__( 'Fundido', 'pro-elements' ),
					'scale' => esc_html__( 'Escala', 'pro-elements' ),
				),
				'style_transfer' => false,
			)
		);

		$this->add_control(
			'speed',
			array(
				'label'       => esc_html__( 'Duración de la transición (ms)', 'pro-elements' ),
				'description' => esc_html__( 'Con movimiento reducido, el cambio es inmediato.', 'pro-elements' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 3000,
				'step'        => 50,
				'placeholder' => '500',
				'selectors'   => array( '{{WRAPPER}} .digi-nslider' => '--digi-ns-speed: {{VALUE}}ms;' ),
				'condition'   => array( 'effect!' => 'slide' ),
			)
		);

		$this->add_control(
			'navigation',
			array(
				'label'          => esc_html__( 'Navegación', 'pro-elements' ),
				'description'    => esc_html__( 'Siempre queda una forma de cambiar de diapositiva sin arrastrar: no se pueden quitar a la vez flechas y puntos.', 'pro-elements' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => 'both',
				'options'        => array(
					'both'   => esc_html__( 'Flechas y puntos', 'pro-elements' ),
					'arrows' => esc_html__( 'Sólo flechas', 'pro-elements' ),
					'dots'   => esc_html__( 'Sólo puntos', 'pro-elements' ),
				),
				'separator'      => 'before',
				'style_transfer' => false,
			)
		);

		$this->add_control( 'counter', self::counter_control() );

		$this->add_control(
			'arrows_position',
			array(
				'label'          => esc_html__( 'Flechas', 'pro-elements' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => 'bar',
				'options'        => array(
					'bar'   => esc_html__( 'Debajo, con los puntos', 'pro-elements' ),
					'sides' => esc_html__( 'A los lados', 'pro-elements' ),
				),
				'condition'      => array( 'navigation!' => 'dots' ),
				'style_transfer' => false,
			)
		);

		$this->add_control(
			'loop',
			array(
				'label'          => esc_html__( 'Bucle', 'pro-elements' ),
				'description'    => esc_html__( 'Después de la última diapositiva, las flechas vuelven a la primera.', 'pro-elements' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => 'yes',
				'style_transfer' => false,
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'          => esc_html__( 'Reproducción automática', 'pro-elements' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => '',
				'separator'      => 'before',
				'style_transfer' => false,
			)
		);

		$this->add_control(
			'autoplay_delay',
			array(
				'label'          => esc_html__( 'Tiempo por diapositiva (ms)', 'pro-elements' ),
				'type'           => Controls_Manager::NUMBER,
				'default'        => self::DELAY_DEFAULT,
				'min'            => self::DELAY_MIN,
				'max'            => self::DELAY_MAX,
				'step'           => 500,
				'condition'      => array( 'autoplay' => 'yes' ),
				'style_transfer' => false,
			)
		);

		$this->add_control(
			'pause_on_hover',
			array(
				'label'          => esc_html__( 'Pausar al pasar el puntero', 'pro-elements' ),
				'type'           => Controls_Manager::SWITCHER,
				'default'        => 'yes',
				'condition'      => array( 'autoplay' => 'yes' ),
				'style_transfer' => false,
			)
		);

		$this->add_control(
			'autoplay_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Se muestra el botón «Pausar». También se detiene al enfocar el slider, fuera de la pantalla, en el editor y con movimiento reducido.', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( 'autoplay' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_slides_style',
			array(
				'label' => esc_html__( 'Diapositivas', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'slider_height',
			array(
				'label'       => esc_html__( 'Altura fija', 'pro-elements' ),
				'description' => esc_html__( 'Vacío: la de la diapositiva más alta, que no cambia entre pases.', 'pro-elements' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px', 'vh', 'em', 'rem' ),
				'range'       => array(
					'px' => array( 'min' => 80, 'max' => 1200 ),
					'vh' => array( 'min' => 10, 'max' => 100 ),
				),
				'selectors'   => array( '{{WRAPPER}} .digi-nslider__track' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_nav_style',
			array(
				'label' => esc_html__( 'Flechas, puntos y pausa', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'nav_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-nslider' => '--digi-ns-fg: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'nav_background',
			array(
				'label'     => esc_html__( 'Fondo de los botones', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-nslider' => '--digi-ns-bg: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'nav_contrast_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Elige un color con un contraste de 3:1 como mínimo frente al fondo de los botones y de la página.', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->add_control(
			'nav_size',
			array(
				'label'       => esc_html__( 'Tamaño de los botones', 'pro-elements' ),
				'description' => esc_html__( 'Nunca menos de 24 px.', 'pro-elements' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 24, 'max' => 96 ) ),
				'selectors'   => array( '{{WRAPPER}} .digi-nslider' => '--digi-ns-btn: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'dot_size',
			array(
				'label'       => esc_html__( 'Tamaño de los puntos', 'pro-elements' ),
				'description' => esc_html__( 'El área que se pulsa mide siempre 24 × 24 px.', 'pro-elements' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 6, 'max' => 20 ) ),
				'selectors'   => array( '{{WRAPPER}} .digi-nslider' => '--digi-ns-dot: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'nav_spacing',
			array(
				'label'      => esc_html__( 'Separación de la navegación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 64 ) ),
				'selectors'  => array( '{{WRAPPER}} .digi-nslider' => '--digi-ns-space: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Contador «2 / 5» (F8, F8-D14; V.10.4): opcional y sin `default`, así que un slider existente pinta el mismo HTML que
	 * en 4.3.0.169. No es de estilo (`style_transfer => false`). Su hoja y su script llegan por `assets`, sólo a las
	 * páginas que lo activan; en la vista previa del editor los encola `Module::preview_counter_*()`.
	 */
	public static function counter_control(): array {
		$active = array(
			'terms' => array(
				array(
					'name'     => 'counter',
					'operator' => '!==',
					'value'    => '',
				),
			),
		);
		return array(
			'label'          => esc_html__( 'Contador (2 / 5)', 'pro-elements' ),
			'description'    => esc_html__( 'Muestra el número de la diapositiva actual y el total. Los lectores de pantalla no lo repiten: cada diapositiva ya se anuncia como «N de M». Sin JavaScript no se ve.', 'pro-elements' ),
			'type'           => Controls_Manager::SWITCHER,
			'return_value'   => 'yes',
			'style_transfer' => false,
			'assets'         => array(
				'styles'  => array(
					array(
						'name'       => self::COUNTER,
						'conditions' => $active,
					),
				),
				'scripts' => array(
					array(
						'name'       => self::COUNTER,
						'conditions' => $active,
					),
				),
			),
		);
	}

	/** Nombre de cada punto, con `%d` en lugar del número (traducible y escapado para un atributo). */
	public static function dot_label(): string {
		/* translators: %d: número de la diapositiva. */
		return esc_attr__( 'Ir a la diapositiva %d', 'pro-elements' );
	}

	/** Nombre accesible por defecto del carrusel (traducible, sin escapar: se escapa al pintarlo). */
	public static function default_label(): string {
		return __( 'Diapositivas', 'pro-elements' );
	}

	/**
	 * Ajustes normalizados para pintar (los mismos que aplica la plantilla del editor): ningún valor guardado, de un kit
	 * o de otra versión, sale fuera de sus listas cerradas.
	 *
	 * @param array $settings Ajustes del widget.
	 * @param int   $count    Diapositivas que se pintan.
	 */
	public static function normalize( array $settings, int $count ): array {
		$effect     = isset( $settings['effect'] ) && in_array( $settings['effect'], self::EFFECTS, true ) ? $settings['effect'] : 'slide';
		$navigation = isset( $settings['navigation'] ) && in_array( $settings['navigation'], self::NAVIGATION, true ) ? $settings['navigation'] : 'both';
		$position   = isset( $settings['arrows_position'] ) && in_array( $settings['arrows_position'], self::POSITIONS, true ) ? $settings['arrows_position'] : 'bar';
		$delay      = isset( $settings['autoplay_delay'] ) && is_numeric( $settings['autoplay_delay'] ) ? (int) $settings['autoplay_delay'] : self::DELAY_DEFAULT;
		$label      = isset( $settings['carousel_label'] ) && is_scalar( $settings['carousel_label'] ) ? trim( wp_strip_all_tags( (string) $settings['carousel_label'] ) ) : '';
		$multi      = $count > 1;
		return array(
			'effect'   => $effect,
			'arrows'   => $multi && 'dots' !== $navigation,
			'dots'     => $multi && 'arrows' !== $navigation,
			'sides'    => 'sides' === $position && 'dots' !== $navigation,
			'loop'     => 'yes' === ( $settings['loop'] ?? '' ),
			'autoplay' => $multi && 'yes' === ( $settings['autoplay'] ?? '' ),
			'delay'    => max( self::DELAY_MIN, min( self::DELAY_MAX, $delay ) ),
			'hover'    => 'yes' === ( $settings['pause_on_hover'] ?? '' ),
			'label'    => '' !== $label ? $label : self::default_label(),
			'counter'  => $multi && 'yes' === ( $settings['counter'] ?? '' ),
		);
	}

	/** Diapositivas que se pintan: filas del repetidor con su contenedor hijo. */
	protected function slide_count( array $settings ): int {
		$rows = isset( $settings['slides'] ) && is_array( $settings['slides'] ) ? count( $settings['slides'] ) : 0;
		return min( $rows, count( (array) $this->get_children() ) );
	}

	protected function render() {
		$settings = (array) $this->get_settings_for_display();
		$count    = $this->slide_count( $settings );
		$s        = self::normalize( $settings, $count );
		$track    = 'digi-nslider-' . $this->get_id();

		$attributes = array(
			'class'                => 'digi-nslider' . ( $s['sides'] ? ' digi-nslider--sides' : '' ),
			'aria-roledescription' => esc_attr__( 'carrusel', 'pro-elements' ),
			'aria-label'           => $s['label'],
			'data-digi-effect'     => $s['effect'],
		);
		if ( $s['loop'] ) {
			$attributes['data-digi-loop'] = '1';
		}
		if ( $s['autoplay'] ) {
			$attributes['data-digi-autoplay'] = (string) $s['delay'];
			if ( $s['hover'] ) {
				$attributes['data-digi-hover'] = '1';
			}
		}

		echo '<section' . self::attributes( $attributes ) . '>';
		if ( $s['autoplay'] ) {
			echo '<button type="button" class="digi-nslider__btn digi-nslider__pause" aria-controls="' . esc_attr( $track ) . '" aria-label="' . esc_attr__( 'Pausar', 'pro-elements' ) . '" data-digi-pause="' . esc_attr__( 'Pausar', 'pro-elements' ) . '" data-digi-play="' . esc_attr__( 'Reanudar', 'pro-elements' ) . '" hidden>' . self::ICON_PAUSE . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icono propio constante.
		}
		if ( $s['arrows'] ) {
			echo '<button type="button" class="digi-nslider__btn digi-nslider__prev" aria-controls="' . esc_attr( $track ) . '" aria-label="' . esc_attr__( 'Diapositiva anterior', 'pro-elements' ) . '">' . self::ICON_PREV . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icono propio constante.
		}
		$dot_label = self::dot_label();
		echo '<div class="digi-nslider__dots" data-digi-dot="' . $dot_label . '"' . ( $s['dots'] ? '' : ' hidden' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba.
		for ( $i = 0; $i < $count; $i++ ) {
			echo '<button type="button" class="digi-nslider__dot" aria-controls="' . esc_attr( $track ) . '" aria-label="' . esc_attr( sprintf( $dot_label, $i + 1 ) ) . '"' . ( 0 === $i ? ' aria-current="true"' : '' ) . '></button>';
		}
		echo '</div>';
		if ( $s['counter'] ) {
			// Tras los puntos (la hoja lo coloca en la rejilla con `+`). `aria-hidden`: cada diapositiva ya se anuncia como
			// «N de M»; `hidden` hasta que el script lo pone al día.
			echo '<p class="digi-nslider__count" aria-hidden="true" hidden><span data-digi-count>1</span> / ' . (int) $count . '</p>';
		}
		if ( $s['arrows'] ) {
			echo '<button type="button" class="digi-nslider__btn digi-nslider__next" aria-controls="' . esc_attr( $track ) . '" aria-label="' . esc_attr__( 'Diapositiva siguiente', 'pro-elements' ) . '">' . self::ICON_NEXT . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icono propio constante.
		}
		echo '<div class="digi-nslider__track" id="' . esc_attr( $track ) . '" aria-live="' . ( $s['autoplay'] ? 'off' : 'polite' ) . '">';
		for ( $i = 0; $i < $count; $i++ ) {
			$this->print_child( $i, array( 'count' => $count ) );
		}
		echo '</div></section>';
	}

	/**
	 * Pinta el contenedor hijo `$index` con los atributos de diapositiva (`role="group"`, `aria-roledescription` y
	 * `aria-label="N de M"`), por el mismo filtro que usa `nested-tabs` del núcleo
	 * (`elementor/modules/nested-tabs/widgets/nested-tabs.php:1130-1152`). Un contenedor anidado dentro del hijo no los
	 * recibe: el filtro sólo actúa sobre el hijo directo.
	 *
	 * @param int   $index         Posición del hijo.
	 * @param array $item_settings `count`: total de diapositivas que se pintan.
	 */
	public function print_child( $index, $item_settings = array() ) {
		$children = (array) $this->get_children();
		if ( ! isset( $children[ $index ] ) ) {
			return;
		}
		$child = $children[ $index ];
		$count = isset( $item_settings['count'] ) ? (int) $item_settings['count'] : count( $children );
		$add   = function ( $should_render, $container = null ) use ( $child, $index, $count ) {
			if ( $container === $child && method_exists( $container, 'add_render_attribute' ) ) {
				$container->add_render_attribute( '_wrapper', self::slide_attributes( (int) $index, $count ) );
			}
			return $should_render;
		};
		add_filter( 'elementor/frontend/container/should_render', $add, 10, 2 );
		$child->print_element();
		remove_filter( 'elementor/frontend/container/should_render', $add, 10 );
	}

	/** Atributos de la diapositiva `$index` (desde 0) de `$count`. */
	public static function slide_attributes( int $index, int $count ): array {
		$strings = self::slide_strings();
		return array(
			'role'                 => 'group',
			'aria-roledescription' => $strings['slide'],
			'aria-label'           => sprintf( $strings['of'], $index + 1, $count ),
		);
	}

	/**
	 * Textos de cada diapositiva, los mismos en el servidor y en el editor (`nested-slider-editor.js`), sin escapar:
	 * el núcleo escapa los atributos de `_wrapper` al pintarlos y el editor los pone con `setAttribute`.
	 */
	public static function slide_strings(): array {
		return array(
			'slide' => __( 'diapositiva', 'pro-elements' ),
			/* translators: 1: número de la diapositiva, 2: total de diapositivas. */
			'of'    => __( '%1$s de %2$s', 'pro-elements' ),
		);
	}

	/** ` nombre="valor"` por atributo, escapados. */
	protected static function attributes( array $attributes ): string {
		$html = '';
		foreach ( $attributes as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}
		return $html;
	}

	/**
	 * Un punto nuevo al añadir una diapositiva en el editor (repetidor mejorado: `target_container` y `node`). Su número
	 * es el de la diapositiva que se crea al final; el script lo renumera si se duplica en medio.
	 */
	protected function content_template_single_repeater_item() {
		?>
		<# var digiN = view.collection.length + 1, digiTrack = 'digi-nslider-' + view.getID(); #>
		<button type="button" class="digi-nslider__dot" aria-controls="{{ digiTrack }}" aria-label="<?php echo str_replace( '%d', '{{ digiN }}', self::dot_label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en dot_label(). ?>"></button>
		<?php
	}

	/**
	 * Plantilla del editor: el mismo marcado que `render()` (los contenedores hijos los coloca el editor en la pista).
	 * `data-digi-start` conserva la diapositiva que se edita al volver a pintar el widget.
	 */
	protected function content_template() {
		?>
		<#
		var digiCount = settings.slides ? settings.slides.length : 0,
			digiMulti = digiCount > 1,
			digiEffect = _.contains( [ 'slide', 'fade', 'scale' ], settings.effect ) ? settings.effect : 'slide',
			digiNav = _.contains( [ 'both', 'arrows', 'dots' ], settings.navigation ) ? settings.navigation : 'both',
			digiArrows = digiMulti && 'dots' !== digiNav,
			digiDots = digiMulti && 'arrows' !== digiNav,
			digiSides = 'sides' === settings.arrows_position && 'dots' !== digiNav,
			digiAuto = digiMulti && 'yes' === settings.autoplay,
			digiCounter = digiMulti && 'yes' === settings.counter,
			digiDelay = Math.max( <?php echo (int) self::DELAY_MIN; ?>, Math.min( <?php echo (int) self::DELAY_MAX; ?>, parseInt( settings.autoplay_delay, 10 ) || <?php echo (int) self::DELAY_DEFAULT; ?> ) ),
			digiLabel = _.isString( settings.carousel_label ) && settings.carousel_label.trim() ? settings.carousel_label.trim() : '<?php echo esc_js( self::default_label() ); ?>',
			digiTrack = 'digi-nslider-' + view.getID(),
			digiModel = view.getEditModel ? view.getEditModel() : null,
			digiEdit = digiModel ? digiModel.get( 'editSettings' ) : null,
			digiStart = digiEdit && digiEdit.get( 'activeItemIndex' ) ? digiEdit.get( 'activeItemIndex' ) : 1;
		#>
		<section class="digi-nslider<# if ( digiSides ) { #> digi-nslider--sides<# } #>" aria-roledescription="<?php echo esc_attr__( 'carrusel', 'pro-elements' ); ?>" aria-label="{{ digiLabel }}" data-digi-effect="{{ digiEffect }}"<# if ( 'yes' === settings.loop ) { #> data-digi-loop="1"<# } #><# if ( digiAuto ) { #> data-digi-autoplay="{{ digiDelay }}"<# if ( 'yes' === settings.pause_on_hover ) { #> data-digi-hover="1"<# } #><# } #> data-digi-start="{{ digiStart }}">
			<# if ( digiAuto ) { #><button type="button" class="digi-nslider__btn digi-nslider__pause" aria-controls="{{ digiTrack }}" aria-label="<?php echo esc_attr__( 'Pausar', 'pro-elements' ); ?>" data-digi-pause="<?php echo esc_attr__( 'Pausar', 'pro-elements' ); ?>" data-digi-play="<?php echo esc_attr__( 'Reanudar', 'pro-elements' ); ?>" hidden><?php echo self::ICON_PAUSE; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icono propio constante. ?></button><# } #>
			<# if ( digiArrows ) { #><button type="button" class="digi-nslider__btn digi-nslider__prev" aria-controls="{{ digiTrack }}" aria-label="<?php echo esc_attr__( 'Diapositiva anterior', 'pro-elements' ); ?>"><?php echo self::ICON_PREV; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icono propio constante. ?></button><# } #>
			<div class="digi-nslider__dots" data-digi-dot="<?php echo self::dot_label(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en dot_label(). ?>"<# if ( ! digiDots ) { #> hidden<# } #>><# _.each( settings.slides, function( item, index ) { #><button type="button" class="digi-nslider__dot" aria-controls="{{ digiTrack }}" aria-label="<?php echo str_replace( '%d', '{{ index + 1 }}', self::dot_label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en dot_label(). ?>"<# if ( index + 1 === digiStart ) { #> aria-current="true"<# } #>></button><# } ); #></div>
			<# if ( digiCounter ) { #><p class="digi-nslider__count" aria-hidden="true" hidden><span data-digi-count>{{ digiStart }}</span> / {{ digiCount }}</p><# } #>
			<# if ( digiArrows ) { #><button type="button" class="digi-nslider__btn digi-nslider__next" aria-controls="{{ digiTrack }}" aria-label="<?php echo esc_attr__( 'Diapositiva siguiente', 'pro-elements' ); ?>"><?php echo self::ICON_NEXT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icono propio constante. ?></button><# } #>
			<div class="digi-nslider__track" id="{{ digiTrack }}" aria-live="<# if ( digiAuto ) { #>off<# } else { #>polite<# } #>"></div>
		</section>
		<?php
	}
}
