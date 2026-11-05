<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Extracto (`digitalisimo-post-excerpt`): sustituye al widget «Post Excerpt» de PRO
 * (`modules/theme-builder/widgets/post-excerpt.php`, mapa `modules/digitalisimo-convert/maps/theme-post-excerpt.php`).
 * Repite sus controles con el mismo nombre, valor por defecto, selectores y condiciones: el texto por defecto es la
 * etiqueta dinámica `post-excerpt` (el extracto de `get_the_excerpt()` con sus filtros, el mismo dato que PRO) y se pinta
 * tal cual, sin envoltura propia, como PRO.
 *
 * Propio: la hoja sólo añade el foco visible de los enlaces del extracto (contorno de 2 px con separación de 2 px) y
 * quita su transición de color con `prefers-reduced-motion`.
 */
class Post_Excerpt extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-post-excerpt';
	}

	public function get_title() {
		return esc_html__( 'Extracto', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-post-excerpt';
	}

	public function get_categories() {
		return array( 'theme-elements-single' );
	}

	public function get_keywords() {
		return array( 'extracto', 'resumen', 'entrada', 'descripción', 'post', 'excerpt', 'description' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	protected function register_controls() {
		$optimized_markup          = \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' ) && ! $this->has_widget_inner_wrapper();
		$widget_container_selector = $optimized_markup ? '' : ' .elementor-widget-container';

		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Contenido', 'pro-elements' ),
			)
		);

		$this->add_control(
			'excerpt',
			array(
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'dynamic'     => array(
					'active'  => true,
					'default' => \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text( null, 'post-excerpt' ),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'start'   => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'     => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'classes'              => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left'  => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'selectors'            => array(
					"{{WRAPPER}}{$widget_container_selector}" => 'text-align: {{VALUE}};',
				),
				'separator'            => 'after',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'selector' => "{{WRAPPER}}{$widget_container_selector}",
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'text_shadow',
				'selector' => '{{WRAPPER}}',
			)
		);

		$this->add_responsive_control(
			'paragraph_spacing',
			array(
				'label'      => esc_html__( 'Espacio entre párrafos', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px' => array(
						'max' => 100,
					),
					'em' => array(
						'min' => 0.1,
						'max' => 20,
					),
				),
				'selectors'  => array(
					"{{WRAPPER}}{$widget_container_selector}" => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'separator',
			array(
				'type' => Controls_Manager::DIVIDER,
			)
		);

		$this->start_controls_tabs( 'link_colors' );

		$this->start_controls_tab(
			'colors_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'selectors' => array(
					"{{WRAPPER}}{$widget_container_selector}" => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'link_color',
			array(
				'label'     => esc_html__( 'Color de los enlaces', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'colors_hover',
			array(
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
			)
		);

		$this->add_control(
			'link_hover_color',
			array(
				'label'     => esc_html__( 'Color de los enlaces', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} a:hover, {{WRAPPER}} a:focus' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'link_hover_color_transition_duration',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array(
					'unit' => 's',
				),
				'selectors'  => array(
					'{{WRAPPER}} a' => 'transition-duration: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/** Como PRO: el ajuste tal cual (la etiqueta dinámica ya devuelve el extracto escapado por WordPress). */
	protected function render() {
		$this->print_unescaped_setting( 'excerpt' );
	}

	/** Como PRO: el extracto en texto plano, salvo en un contenido construido con el editor. */
	public function render_markdown(): string {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		if ( $document && $document->is_built_with_elementor() ) {
			return '';
		}
		return trim( wp_strip_all_tags( (string) get_the_excerpt() ) );
	}
}
