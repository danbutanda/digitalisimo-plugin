<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Indicador de progreso (`digitalisimo-progress-tracker`): sustituye al widget «Progress Tracker» de PRO
 * (`modules/progress-tracker/widgets/progress-tracker.php`, mapa `modules/digitalisimo-convert/maps/progress-tracker.php`).
 * Repite sus controles con el mismo nombre, valor por defecto y condiciones, y las mismas variables CSS que fijan sus
 * estilos (`--horizontal-height`, `--circular-color`…: el CSS personalizado que las use sigue valiendo); el marcado, las
 * clases, la hoja y el script son propios (sin jQuery ni los paquetes de PRO).
 *
 * - Barra (horizontal) o círculo (SVG), con el porcentaje opcional.
 * - Seguimiento de la página entera, del contenido de la entrada (`.elementor-widget-theme-post-content`) o de un
 *   selector, con las mismas fórmulas que PRO: la página cuenta desde arriba hasta el final del desplazamiento; un
 *   elemento, desde que su borde superior entra por abajo hasta que su borde inferior llega abajo.
 * - Es una ayuda visual del desplazamiento: va con `aria-hidden="true"`, como el «Progreso de lectura» propio.
 *
 * Diferencias declaradas con PRO: la alineación del círculo se aplica en cada dispositivo (PRO sólo usaba la del
 * escritorio, como clase), con «Scroll Snap» se mide el desplazamiento del `body` sin envolver la página en otro
 * elemento (PRO movía todo el `body` dentro de `#e-scroll-snap-container`), un selector que no existe o no es válido deja
 * el indicador a cero sin errores, y la actualización se agrupa por fotograma.
 */
class Progress_Tracker extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-progress-tracker';
	}

	public function get_title() {
		return esc_html__( 'Indicador de progreso', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-progress-tracker';
	}

	public function get_categories() {
		return array( 'digitalisimo', 'theme-elements-single' );
	}

	public function get_keywords() {
		return array( 'progreso', 'progress', 'tracker', 'lectura', 'read', 'desplazamiento', 'scroll', 'barra', 'círculo' );
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	/** Estilos de borde de PRO. */
	private static function border_styles(): array {
		return array(
			'none'   => esc_html__( 'Ninguno', 'pro-elements' ),
			'solid'  => esc_html__( 'Sólido', 'pro-elements' ),
			'double' => esc_html__( 'Doble', 'pro-elements' ),
			'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
			'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
			'groove' => esc_html__( 'Ranurado', 'pro-elements' ),
		);
	}

	private function register_content_controls() {
		$this->start_controls_section(
			'section_content_scrolling_tracker',
			array(
				'label' => esc_html__( 'Indicador de progreso', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'type',
			array(
				'label'   => esc_html__( 'Tipo de indicador', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'horizontal' => esc_html__( 'Barra horizontal', 'pro-elements' ),
					'circular'   => esc_html__( 'Círculo', 'pro-elements' ),
				),
				'default' => 'horizontal',
			)
		);

		$this->add_control(
			'relative_to',
			array(
				'label'   => esc_html__( 'Progreso respecto a', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'entire_page'  => esc_html__( 'Toda la página', 'pro-elements' ),
					'post_content' => esc_html__( 'Contenido de la entrada', 'pro-elements' ),
					'selector'     => esc_html__( 'Selector', 'pro-elements' ),
				),
				'default' => 'entire_page',
			)
		);

		$this->add_control(
			'selector',
			array(
				'label'       => esc_html__( 'Selector', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'description' => esc_html__( 'ID o clase CSS de un elemento de esta página para seguir su progreso por separado.', 'pro-elements' ),
				'ai'          => array(
					'active' => false,
				),
				'condition'   => array(
					'relative_to' => 'selector',
				),
				'placeholder' => '#id, .clase',
			)
		);

		$this->add_control(
			'relative_to_description',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'El progreso respecto al contenido de la entrada sólo funciona en una plantilla de entrada individual.', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array(
					'relative_to' => 'post_content',
				),
			)
		);

		$this->add_control(
			'direction',
			array(
				'label'       => esc_html__( 'Dirección', 'pro-elements' ),
				'type'        => Controls_Manager::CHOOSE,
				'options'     => array(
					'ltr' => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'rtl' => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'render_type' => 'template',
				'selectors'   => array(
					'{{WRAPPER}}' => '--direction: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'percentage',
			array(
				'label'     => esc_html__( 'Porcentaje', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'no',
			)
		);

		$this->add_responsive_control(
			'percentage_position',
			array(
				'label'     => esc_html__( 'Posición del porcentaje', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'rtl' => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'ltr' => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'condition' => array(
					'type'       => 'horizontal',
					'percentage' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--text-direction: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_tracker_style_controls() {
		$units = array( 'px', '%', 'em', 'rem', 'vw', 'custom' );
		$wide  = array(
			'px'  => array(
				'max' => 400,
			),
			'em'  => array(
				'max' => 40,
			),
			'rem' => array(
				'max' => 40,
			),
		);

		$this->start_controls_section(
			'section_style_scrolling_tracker',
			array(
				'label' => esc_html__( 'Indicador', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'circular_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 200,
					),
					'em'  => array(
						'max' => 20,
					),
					'rem' => array(
						'max' => 20,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--circular-width: {{SIZE}}{{UNIT}}; --circular-height: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'type' => 'circular',
				),
			)
		);

		$this->add_control(
			'heading_progress_style',
			array(
				'type'  => Controls_Manager::HEADING,
				'label' => esc_html__( 'Indicador de avance', 'pro-elements' ),
			)
		);

		$this->add_control(
			'circular_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--circular-color: {{VALUE}}',
				),
				'condition' => array(
					'type' => 'circular',
				),
			)
		);

		$this->add_responsive_control(
			'circular_width',
			array(
				'label'      => esc_html__( 'Grosor', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $units,
				'range'      => $wide,
				'selectors'  => array(
					'{{WRAPPER}}' => '--circular-progress-width: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'type' => 'circular',
				),
			)
		);

		// PRO sólo usaba la alineación del escritorio (como clase); aquí cada dispositivo aplica la suya.
		$this->add_responsive_control(
			'align',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'left'   => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors_dictionary' => array(
					'left'   => 'margin-left: 0; margin-right: auto',
					'center' => 'margin-left: auto; margin-right: auto',
					'right'  => 'margin-left: auto; margin-right: 0',
				),
				'selectors'            => array(
					'{{WRAPPER}} .digi-progress-tracker' => '{{VALUE}};',
				),
				'condition'            => array(
					'type' => 'circular',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'horizontal_color',
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => '{{WRAPPER}} .digi-progress-tracker__progress',
				'condition'      => array(
					'type' => 'horizontal',
				),
				'fields_options' => array(
					'background' => array(
						'label' => esc_html__( 'Color del avance', 'pro-elements' ),
					),
				),
			)
		);

		$this->add_control(
			'horizontal_border_style',
			array(
				'label'     => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'none',
				'options'   => self::border_styles(),
				'selectors' => array(
					'{{WRAPPER}}' => '--horizontal-progress-border: {{VALUE}};',
				),
				'condition' => array(
					'type' => 'horizontal',
				),
			)
		);

		$this->add_responsive_control(
			'horizontal_border_width',
			array(
				'label'      => esc_html__( 'Ancho', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => $units,
				'selectors'  => array(
					'{{WRAPPER}}' => '--horizontal-progress-border-top-width: {{TOP}}{{UNIT}}; --horizontal-progress-border-right-width: {{RIGHT}}{{UNIT}}; --horizontal-progress-border-bottom-width: {{BOTTOM}}{{UNIT}}; --horizontal-progress-border-left-width: {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'horizontal_border_style!' => 'none',
					'type'                     => 'horizontal',
				),
			)
		);

		$this->add_control(
			'horizontal_border_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--horizontal-progress-border-color: {{VALUE}}',
				),
				'condition' => array(
					'horizontal_border_style!' => 'none',
					'type'                     => 'horizontal',
				),
			)
		);

		$this->add_responsive_control(
			'horizontal_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--progress-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'type' => 'horizontal',
				),
			)
		);

		$this->add_control(
			'heading_tracker_background_style',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Fondo del indicador', 'pro-elements' ),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'circular_background_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--circular-background-color: {{VALUE}}',
				),
				'condition' => array(
					'type' => 'circular',
				),
			)
		);

		$this->add_responsive_control(
			'circular_background_width',
			array(
				'label'      => esc_html__( 'Grosor', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $units,
				'range'      => $wide,
				'selectors'  => array(
					'{{WRAPPER}}' => '--circular-background-width: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'type' => 'circular',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'horizontal_background_color',
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => '{{WRAPPER}} .digi-progress-tracker--horizontal',
				'fields_options' => array(
					'background' => array(
						'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
					),
				),
				'condition'      => array(
					'type' => 'horizontal',
				),
			)
		);

		$this->add_responsive_control(
			'horizontal_height',
			array(
				'label'      => esc_html__( 'Alto', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--horizontal-height: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'type' => 'horizontal',
				),
			)
		);

		$this->add_control(
			'horizontal_tracker_border_style',
			array(
				'label'     => esc_html__( 'Tipo de borde', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'none',
				'options'   => self::border_styles(),
				'selectors' => array(
					'{{WRAPPER}}' => '--horizontal-border-style: {{VALUE}};',
				),
				'condition' => array(
					'type' => 'horizontal',
				),
			)
		);

		$this->add_responsive_control(
			'horizontal_tracker_border_width',
			array(
				'label'      => esc_html__( 'Ancho', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => $units,
				'selectors'  => array(
					'{{WRAPPER}}' => '--horizontal-border-top-width: {{TOP}}{{UNIT}}; --horizontal-border-right-width: {{RIGHT}}{{UNIT}}; --horizontal-border-bottom-width: {{BOTTOM}}{{UNIT}}; --horizontal-border-left-width: {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'horizontal_tracker_border_style!' => 'none',
					'type'                             => 'horizontal',
				),
			)
		);

		$this->add_control(
			'horizontal_tracker_border_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--horizontal-border-color: {{VALUE}}',
				),
				'condition' => array(
					'horizontal_tracker_border_style!' => 'none',
					'type'                             => 'horizontal',
				),
			)
		);

		$this->add_responsive_control(
			'horizontal_tracker_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'type' => 'horizontal',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'      => 'box_shadow',
				'selector'  => '{{WRAPPER}} .digi-progress-tracker',
				'condition' => array(
					'type' => 'horizontal',
				),
			)
		);

		$this->add_responsive_control(
			'horizontal_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => $units,
				'selectors'  => array(
					'{{WRAPPER}}' => '--tracker-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'type' => 'horizontal',
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_content_style_controls() {
		$this->start_controls_section(
			'section__content_style_scrolling_tracker',
			array(
				'label'     => esc_html__( 'Contenido', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'percentage' => 'yes',
				),
			)
		);

		$this->add_control(
			'heading_percentage_style',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Porcentaje', 'pro-elements' ),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'percentage_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--percentage-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'percentage_typography',
				'selector' => '{{WRAPPER}} .digi-progress-tracker__percentage',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'percentage_text_shadow',
				'selector' => '{{WRAPPER}} .digi-progress-tracker__percentage',
			)
		);

		$this->end_controls_section();
	}

	protected function register_controls() {
		$this->register_content_controls();
		$this->register_tracker_style_controls();
		$this->register_content_style_controls();
	}

	public function render_plain_content() {}

	protected function render() {
		$s        = $this->get_settings_for_display();
		$circular = 'circular' === ( $s['type'] ?? 'horizontal' );
		$relative = in_array( $s['relative_to'] ?? '', array( 'entire_page', 'post_content', 'selector' ), true ) ? $s['relative_to'] : 'entire_page';
		$attrs    = array(
			'class'              => 'digi-progress-tracker digi-progress-tracker--' . ( $circular ? 'circular' : 'horizontal' ),
			'aria-hidden'        => 'true',
			'data-digi-relative' => $relative,
		);
		if ( 'selector' === $relative ) {
			$attrs['data-digi-selector'] = trim( (string) ( $s['selector'] ?? '' ) );
		}
		if ( in_array( $s['direction'] ?? '', array( 'ltr', 'rtl' ), true ) ) {
			$attrs['data-digi-direction'] = $s['direction'];
		}
		if ( 'yes' === ( $s['percentage'] ?? '' ) ) {
			$attrs['data-digi-percentage'] = 'yes';
		}
		$html = '';
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		$percentage = '<div class="digi-progress-tracker__percentage"></div>';
		if ( $circular ) {
			$inner = '<svg width="100%" height="100%" focusable="false"><circle class="digi-progress-tracker__track" r="40%" cx="50%" cy="50%"/><circle class="digi-progress-tracker__progress" r="40%" cx="50%" cy="50%"/></svg>' . $percentage;
		} else {
			$inner = '<div class="digi-progress-tracker__progress">' . $percentage . '</div>';
		}
		echo '<div' . $html . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados; el resto es fijo.
	}

	public function render_markdown(): string {
		$s        = $this->get_settings_for_display();
		$relative = (string) ( $s['relative_to'] ?? 'entire_page' );
		$labels   = array(
			'entire_page'  => esc_html__( 'Toda la página', 'pro-elements' ),
			'post_content' => esc_html__( 'Contenido de la entrada', 'pro-elements' ),
			'selector'     => esc_html__( 'Selector', 'pro-elements' ),
		);
		$lines    = array( '- **' . esc_html__( 'Progreso respecto a', 'pro-elements' ) . ':** ' . ( $labels[ $relative ] ?? $relative ) );
		if ( 'selector' === $relative && '' !== trim( (string) ( $s['selector'] ?? '' ) ) ) {
			$lines[] = '- **' . esc_html__( 'Selector', 'pro-elements' ) . ':** `' . trim( (string) $s['selector'] ) . '`';
		}
		$lines[] = '- **' . esc_html__( 'Tipo de indicador', 'pro-elements' ) . ':** ' . ( 'circular' === ( $s['type'] ?? 'horizontal' ) ? esc_html__( 'Círculo', 'pro-elements' ) : esc_html__( 'Barra horizontal', 'pro-elements' ) );
		return implode( "\n", $lines );
	}
}
