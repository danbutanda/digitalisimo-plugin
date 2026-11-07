<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Tabla de contenido (`digitalisimo-table-of-contents`): sustituye al widget «Table of Contents» de PRO
 * (`modules/table-of-contents/widgets/table-of-contents.php`, mapa `modules/digitalisimo-convert/maps/table-of-contents.php`).
 * Repite sus controles con el mismo nombre, valor por defecto, opciones y condiciones; el marcado, las clases, las
 * variables y el script son propios (sin jQuery ni DOMPurify).
 *
 * Como en PRO, la lista se construye en el navegador (`assets/js/table-of-contents.js`) con los encabezados de las
 * etiquetas elegidas dentro del contenedor indicado, del documento emergente que contiene el widget o de los documentos
 * de la página que no son cabecera, pie ni emergente; descarta los que están dentro de un selector excluido y los
 * títulos de las tablas de contenido. Antes de cada encabezado sin id (ni id en su widget) pone un ancla con el mismo id
 * que PRO (`elementor-toc__heading-anchor-N`), así los enlaces que ya apuntaban a ella siguen funcionando; en el editor no
 * añade anclas. Vista jerárquica o plana, números (`ol` con contadores) o viñetas con el icono elegido (pintado aquí en una
 * `<template>`), mensaje sin encabezados (otra `<template>`), caja minimizable con su punto de corte (evaluado al cargar,
 * como PRO), subelementos plegables y enlace activo según los encabezados a la vista (`IntersectionObserver`). El salto a
 * un encabezado es el del navegador: suave sólo sin «reducir movimiento» (hoja del núcleo).
 *
 * Diferencias declaradas con PRO: `<nav>` con el título como nombre accesible; un solo `<button>` real con
 * `aria-expanded` y `aria-controls` (PRO pintaba dos `div` con `role="button"` que se alternaban), de 24 × 24 px como
 * mínimo sin mover el icono; foco visible propio; indicador de carga propio en CSS (PRO usaba la fuente de iconos del
 * núcleo) que se detiene con «reducir movimiento»; minimizar y plegar sin la animación de deslizamiento de jQuery;
 * enlace activo con `aria-current`. La sombra de la caja va donde la ponía PRO: con el marcado heredado, en el
 * envoltorio (recta bajo la caja redondeada); con el optimizado, en la caja.
 */
class Table_Of_Contents extends \Elementor\Widget_Base {

	/** Etiquetas que la tabla puede leer (como el control de PRO). */
	const TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

	/** Etiquetas permitidas para el título (las del control). */
	const TITLE_TAGS = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' );

	public function get_name() {
		return 'digitalisimo-table-of-contents';
	}

	public function get_title() {
		return esc_html__( 'Tabla de contenido', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-table-of-contents';
	}

	public function get_categories() {
		return array( 'digitalisimo', 'theme-elements-single' );
	}

	public function get_keywords() {
		return array( 'tabla de contenido', 'índice', 'toc', 'encabezados', 'anclas', 'navegación' );
	}

	public function get_style_depends() {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends() {
		return Module::script_depends( $this->get_name() );
	}

	/** Puntos de corte activos del núcleo (sin el gestor, ninguno). */
	private static function breakpoints(): array {
		$plugin = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance : null;
		if ( ! is_object( $plugin ) || ! isset( $plugin->breakpoints ) || ! is_object( $plugin->breakpoints ) || ! method_exists( $plugin->breakpoints, 'get_active_breakpoints' ) ) {
			return array();
		}
		return (array) $plugin->breakpoints->get_active_breakpoints();
	}

	protected function register_controls() {
		$this->start_controls_section(
			'table_of_contents',
			array(
				'label' => esc_html__( 'Tabla de contenido', 'pro-elements' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => esc_html__( 'Título', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'label_block' => true,
				'default'     => esc_html__( 'Tabla de contenido', 'pro-elements' ),
			)
		);

		$this->add_control(
			'html_tag',
			array(
				'label'   => esc_html__( 'Etiqueta HTML', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'h5'  => 'H5',
					'h6'  => 'H6',
					'div' => 'div',
				),
				'default' => 'h4',
			)
		);

		$this->start_controls_tabs( 'include_exclude_tags', array( 'separator' => 'before' ) );

		$this->start_controls_tab( 'include', array( 'label' => esc_html__( 'Incluir', 'pro-elements' ) ) );

		$this->add_control(
			'headings_by_tags',
			array(
				'label'       => esc_html__( 'Anclas por etiquetas', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => array( 'h2', 'h3', 'h4', 'h5', 'h6' ),
				'options'     => array(
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				),
				'label_block' => true,
			)
		);

		$this->add_control(
			'container',
			array(
				'label'       => esc_html__( 'Contenedor', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'ai'          => array( 'active' => false ),
				'label_block' => true,
				'description' => esc_html__( 'Limita la tabla de contenido a los encabezados que están dentro de este contenedor (un selector CSS).', 'pro-elements' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'exclude', array( 'label' => esc_html__( 'Excluir', 'pro-elements' ) ) );

		$this->add_control(
			'exclude_headings_by_selector',
			array(
				'label'       => esc_html__( 'Anclas por selector', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'description' => esc_html__( 'Selectores CSS, separados por comas', 'pro-elements' ),
				'default'     => array(),
				'label_block' => true,
				'ai'          => array( 'active' => false ),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'marker_view',
			array(
				'label'     => esc_html__( 'Marcador', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'numbers',
				'options'   => array(
					'numbers' => esc_html__( 'Números', 'pro-elements' ),
					'bullets' => esc_html__( 'Viñetas', 'pro-elements' ),
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'icon',
			array(
				'label'                  => esc_html__( 'Icono', 'pro-elements' ),
				'type'                   => Controls_Manager::ICONS,
				'default'                => array(
					'value'   => 'fas fa-circle',
					'library' => 'fa-solid',
				),
				'recommended'            => array(
					'fa-solid'   => array( 'circle', 'dot-circle', 'square-full' ),
					'fa-regular' => array( 'circle', 'dot-circle', 'square-full' ),
				),
				'condition'              => array( 'marker_view' => 'bullets' ),
				'skin'                   => 'inline',
				'label_block'            => false,
				'exclude_inline_options' => array( 'svg' ),
			)
		);

		$this->add_control(
			'no_headings_message',
			array(
				'label'       => esc_html__( 'Mensaje sin encabezados', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'No se encontraron encabezados en esta página.', 'pro-elements' ),
				'dynamic'     => array( 'active' => true ),
				'label_block' => true,
				'separator'   => 'before',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'additional_options',
			array(
				'label' => esc_html__( 'Opciones adicionales', 'pro-elements' ),
			)
		);

		$this->add_control(
			'word_wrap',
			array(
				'label'        => esc_html__( 'Recortar en una línea', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'ellipsis',
				'prefix_class' => 'digi-toc--content-',
			)
		);

		$this->add_control(
			'minimize_box',
			array(
				'label'     => esc_html__( 'Caja minimizable', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'expand_icon',
			array(
				'label'       => esc_html__( 'Icono para desplegar', 'pro-elements' ),
				'type'        => Controls_Manager::ICONS,
				'default'     => array(
					'value'   => 'fas fa-chevron-down',
					'library' => 'fa-solid',
				),
				'recommended' => array(
					'fa-solid'   => array( 'chevron-down', 'angle-down', 'angle-double-down', 'caret-down', 'caret-square-down' ),
					'fa-regular' => array( 'caret-square-down' ),
				),
				'skin'        => 'inline',
				'label_block' => false,
				'condition'   => array( 'minimize_box' => 'yes' ),
			)
		);

		$this->add_control(
			'collapse_icon',
			array(
				'label'       => esc_html__( 'Icono para plegar', 'pro-elements' ),
				'type'        => Controls_Manager::ICONS,
				'default'     => array(
					'value'   => 'fas fa-chevron-up',
					'library' => 'fa-solid',
				),
				'recommended' => array(
					'fa-solid'   => array( 'chevron-up', 'angle-up', 'angle-double-up', 'caret-up', 'caret-square-up' ),
					'fa-regular' => array( 'caret-square-up' ),
				),
				'skin'        => 'inline',
				'label_block' => false,
				'condition'   => array( 'minimize_box' => 'yes' ),
			)
		);

		// Como PRO: un valor por punto de corte activo (salvo el de pantallas anchas) y el escritorio.
		$minimized_on_options = array();
		foreach ( self::breakpoints() as $breakpoint_key => $breakpoint ) {
			if ( 'widescreen' === $breakpoint_key || ! is_object( $breakpoint ) ) {
				continue;
			}
			$minimized_on_options[ $breakpoint_key ] = sprintf(
				/* translators: 1: nombre del punto de corte, 2: operador de comparación, 3: ancho en píxeles. */
				esc_html__( '%1$s (%2$s %3$dpx)', 'pro-elements' ),
				$breakpoint->get_label(),
				'<',
				$breakpoint->get_value()
			);
		}
		$minimized_on_options['desktop'] = esc_html__( 'Escritorio (o menor)', 'pro-elements' );

		$this->add_control(
			'minimized_on',
			array(
				'label'        => esc_html__( 'Minimizada en', 'pro-elements' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'tablet',
				'options'      => $minimized_on_options,
				'prefix_class' => 'digi-toc--minimized-on-',
				'condition'    => array( 'minimize_box!' => '' ),
			)
		);

		$this->add_control(
			'hierarchical_view',
			array(
				'label'     => esc_html__( 'Vista jerárquica', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'collapse_subitems',
			array(
				'label'       => esc_html__( 'Plegar subelementos', 'pro-elements' ),
				'type'        => Controls_Manager::SWITCHER,
				'description' => esc_html__( 'Úsalo sólo si la tabla de contenido está fijada (sticky): los subelementos se despliegan al llegar a su sección.', 'pro-elements' ),
				'condition'   => array( 'hierarchical_view' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'box_style',
			array(
				'label' => esc_html__( 'Caja', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-bg: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'border_color',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-bc: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'loader_color',
			array(
				'label'     => esc_html__( 'Color del indicador de carga', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-toc__spinner' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 20 ),
					'em'  => array( 'max' => 2 ),
					'rem' => array( 'max' => 2 ),
				),
				'selectors'  => array( '{{WRAPPER}}' => '--digi-toc-bw: {{SIZE}}{{UNIT}}' ),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}}' => '--digi-toc-br: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_responsive_control(
			'header_separator_width',
			array(
				'label'      => esc_html__( 'Ancho del separador', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}}' => '--digi-toc-sw: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_responsive_control(
			'padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array( '{{WRAPPER}}' => '--digi-toc-pad: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_responsive_control(
			'min_height',
			array(
				'label'      => esc_html__( 'Altura mínima', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 1000 ),
					'em'  => array( 'max' => 100 ),
					'rem' => array( 'max' => 100 ),
				),
				'selectors'  => array( '{{WRAPPER}}' => '--digi-toc-mh: {{SIZE}}{{UNIT}}' ),
				'separator'  => 'after',
			)
		);

		// La sombra, donde la ponía PRO (`{{WRAPPER}}`): con el marcado heredado (con `.elementor-widget-container`), en el
		// envoltorio, que no tiene el radio de la caja (PRO lo ponía en el contenedor interior), así que la sombra es recta
		// bajo la caja redondeada; con el marcado optimizado, en la caja, que es el envoltorio de PRO.
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'selector' => '{{WRAPPER}}:has(> .elementor-widget-container), {{WRAPPER}}:not(:has(> .elementor-widget-container)) .digi-toc',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'header_style',
			array(
				'label' => esc_html__( 'Cabecera', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'header_text_align',
			array(
				'label'     => esc_html__( 'Alineación del texto', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'start'  => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'    => array(
						'title' => esc_html__( 'Fin', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'start',
				'classes'   => 'elementor-control-start-end',
				'selectors' => array( '{{WRAPPER}} .digi-toc__title' => 'text-align: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'header_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-hbg: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'header_text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=secondary' ),
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-hc: {{VALUE}}' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'header_typography',
				'selector' => '{{WRAPPER}} .digi-toc__header, {{WRAPPER}} .digi-toc__title',
				'global'   => array( 'default' => 'globals/typography?id=primary' ),
			)
		);

		$this->add_control(
			'toggle_button_color',
			array(
				'label'     => esc_html__( 'Color del icono', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array( 'minimize_box' => 'yes' ),
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-tc: {{VALUE}}' ),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'toggle_button_position',
			array(
				'label'     => esc_html__( 'Posición del icono', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'row-reverse' => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'row'         => array(
						'title' => esc_html__( 'Fin', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'default'   => 'row',
				'toggle'    => false,
				'classes'   => 'elementor-control-start-end',
				'selectors' => array( '{{WRAPPER}} .digi-toc__header' => 'flex-direction: {{VALUE}};' ),
				'condition' => array( 'minimize_box' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'heading_gap',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px' => array( 'max' => 100 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-toc__header' => 'column-gap: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( 'minimize_box' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'list_style',
			array(
				'label' => esc_html__( 'Lista', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'max_height',
			array(
				'label'      => esc_html__( 'Altura máxima', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 1000 ),
					'em'  => array( 'max' => 100 ),
					'rem' => array( 'max' => 100 ),
				),
				'selectors'  => array( '{{WRAPPER}}' => '--digi-toc-maxh: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'list_typography',
				'selector' => '{{WRAPPER}} .digi-toc__item',
				'global'   => array( 'default' => 'globals/typography?id=text' ),
			)
		);

		$this->add_responsive_control(
			'list_indent',
			array(
				'label'      => esc_html__( 'Sangría', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'unit' => 'em' ),
				'selectors'  => array( '{{WRAPPER}}' => '--digi-toc-indent: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->start_controls_tabs( 'item_text_style' );

		$this->start_controls_tab( 'normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );

		$this->add_control(
			'item_text_color_normal',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=text' ),
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-ic: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'item_text_underline_normal',
			array(
				'label'     => esc_html__( 'Subrayado', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-id: underline' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'hover', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );

		$this->add_control(
			'item_text_color_hover',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=accent' ),
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-hoc: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'item_text_underline_hover',
			array(
				'label'     => esc_html__( 'Subrayado', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-hod: underline' ),
			)
		);

		$this->add_control(
			'item_text_hover_transition_duration',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array( 'unit' => 'ms' ),
				'selectors'  => array( '{{WRAPPER}}' => '--digi-toc-it: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'active', array( 'label' => esc_html__( 'Activo', 'pro-elements' ) ) );

		$this->add_control(
			'item_text_color_active',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-ac: {{VALUE}}' ),
			)
		);

		$this->add_control(
			'item_text_underline_active',
			array(
				'label'     => esc_html__( 'Subrayado', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-ad: underline' ),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'heading_marker',
			array(
				'label'     => esc_html__( 'Marcador', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'marker_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=text' ),
				'selectors' => array( '{{WRAPPER}}' => '--digi-toc-mc: {{VALUE}}' ),
			)
		);

		$this->add_responsive_control(
			'marker_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}}' => '--digi-toc-ms: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->end_controls_section();
	}

	/** HTML de un icono del control (del núcleo), decorativo; '' sin icono. */
	private static function icon( $icon ): string {
		if ( ! is_array( $icon ) || empty( $icon['value'] ) || ! class_exists( '\Elementor\Icons_Manager' ) ) {
			return '';
		}
		return (string) \Elementor\Icons_Manager::try_get_icon_html( $icon, array( 'aria-hidden' => 'true' ) );
	}

	/**
	 * Configuración que lee el script: etiquetas válidas, contenedor y exclusión (selectores; el script los prueba), vista,
	 * jerarquía, plegado y, con la caja minimizable, el ancho máximo en el que empieza minimizada (`all`: siempre).
	 */
	public static function config( array $settings ): array {
		$tags   = array_values( array_intersect( array_map( 'strval', (array) ( $settings['headings_by_tags'] ?? array() ) ), self::TAGS ) );
		$string = static function ( $value ) {
			return is_string( $value ) ? trim( $value ) : '';
		};
		$config = array(
			'tags'      => $tags,
			'container' => $string( $settings['container'] ?? '' ),
			'exclude'   => $string( $settings['exclude_headings_by_selector'] ?? '' ),
			'view'      => 'bullets' === ( $settings['marker_view'] ?? '' ) ? 'bullets' : 'numbers',
			'nested'    => ! empty( $settings['hierarchical_view'] ),
			'collapse'  => ! empty( $settings['hierarchical_view'] ) && ! empty( $settings['collapse_subitems'] ),
		);
		if ( 'yes' === ( $settings['minimize_box'] ?? '' ) ) {
			$on  = (string) ( $settings['minimized_on'] ?? 'tablet' );
			$max = 'desktop' === $on ? 'all' : 0;
			foreach ( self::breakpoints() as $key => $breakpoint ) {
				if ( $key === $on && is_object( $breakpoint ) ) {
					$max = (int) $breakpoint->get_value();
				}
			}
			$config['minimize'] = $max;
		}
		return $config;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$id       = 'digi-toc-' . preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $this->get_id() );
		$tag      = in_array( $settings['html_tag'] ?? '', self::TITLE_TAGS, true ) ? $settings['html_tag'] : 'h4';
		$title    = isset( $settings['title'] ) && is_string( $settings['title'] ) ? $settings['title'] : '';
		$minimize = 'yes' === ( $settings['minimize_box'] ?? '' );
		$has      = '' !== trim( $title );

		$label = $has ? ' aria-labelledby="' . esc_attr( $id ) . '-title"' : ' aria-label="' . esc_attr__( 'Tabla de contenido', 'pro-elements' ) . '"';
		$out   = '<nav class="digi-toc"' . $label . ' data-digi-toc="' . esc_attr( (string) wp_json_encode( self::config( $settings ) ) ) . '">';
		if ( $has || $minimize ) {
			$out .= '<div class="digi-toc__header">';
			if ( $has ) {
				$out .= '<' . $tag . ' class="digi-toc__title" id="' . esc_attr( $id ) . '-title">' . wp_kses_post( $title ) . '</' . $tag . '>';
			}
			if ( $minimize ) {
				$out .= '<button type="button" class="digi-toc__toggle" aria-expanded="true" aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'Mostrar u ocultar la tabla de contenido', 'pro-elements' ) . '">'
					. '<span class="digi-toc__toggle-icon digi-toc__toggle-icon--expand">' . self::icon( $settings['expand_icon'] ?? null ) . '</span>'
					. '<span class="digi-toc__toggle-icon digi-toc__toggle-icon--collapse">' . self::icon( $settings['collapse_icon'] ?? null ) . '</span>'
					. '</button>';
			}
			$out .= '</div>';
		}
		$out .= '<div class="digi-toc__body' . ( ! empty( $settings['hierarchical_view'] ) && ! empty( $settings['collapse_subitems'] ) ? ' digi-toc__body--collapsible' : '' ) . '" id="' . esc_attr( $id ) . '">';
		$out .= '<span class="digi-toc__spinner" aria-hidden="true"></span></div>';
		if ( 'bullets' === ( $settings['marker_view'] ?? '' ) ) {
			$marker = self::icon( $settings['icon'] ?? null );
			if ( '' !== $marker ) {
				$out .= '<template class="digi-toc__marker">' . $marker . '</template>';
			}
		}
		$out .= '<template class="digi-toc__empty">' . wp_kses_post( (string) ( $settings['no_headings_message'] ?? '' ) ) . '</template>';
		$out .= '</nav>';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba.
	}

	/** Markdown: el título como encabezado de su nivel (la lista se construye en el navegador). */
	public function render_markdown(): string {
		$settings = $this->get_settings_for_display();
		$title    = trim( wp_strip_all_tags( (string) ( $settings['title'] ?? '' ) ) );
		if ( '' === $title ) {
			return '';
		}
		$level = preg_match( '/^h([2-6])$/', (string) ( $settings['html_tag'] ?? '' ), $m ) ? (int) $m[1] : 2;
		return str_repeat( '#', $level ) . ' ' . $title;
	}
}
