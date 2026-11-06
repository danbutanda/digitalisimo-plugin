<?php
namespace Digitalisimo\Elements\Loop;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Utils;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Piel base de Entradas y Entradas del archivo: repite `modules/posts/skins/skin-base.php` de PRO (mismos controles con
 * el mismo nombre, prefijados por la piel como en PRO —`classic_columns`, `cards_thumbnail`…—, los mismos valores por
 * defecto, opciones y condiciones) con marcado y clases propios:
 *
 *     <div class="digi-posts digi-posts--skin-classic elementor-grid" role="list" data-digi-items [data-digi-masonry]>
 *       <article class="digi-posts__item elementor-grid-item {post_class()}" role="listitem">
 *         <a class="digi-posts__thumb-link" href tabindex="-1"><div class="digi-posts__thumb"><img …></div></a>
 *         <div class="digi-posts__text">
 *           <h3 class="digi-posts__title"><a href>…</a></h3>
 *           <div class="digi-posts__meta"><span class="digi-posts__author">…</span><span class="digi-posts__date">…</span>…</div>
 *           <div class="digi-posts__excerpt"><p>…</p></div>
 *           <a class="digi-posts__more" href tabindex="-1">Leer más »<span class="elementor-screen-only">: título</span></a>
 *         </div>
 *       </article>
 *     </div>
 *     {pie: Posts_Base::render_pagination()}
 *
 * La rejilla es la del núcleo (`elementor-grid`, clases `elementor-grid{-dispositivo}-N` de «Columnas» y variables
 * `--grid-column-gap`/`--grid-row-gap`). Las clases de presentación de PRO (`elementor-posts--thumbnail-*`,
 * `--align-*`…) pasan a `digi-posts--*`, en el mismo nodo (el envoltorio del widget).
 *
 * Diferencias con PRO: la proporción de la imagen se aplica con CSS (PRO esperaba a su script para recolocar la imagen),
 * «Leer más» conserva su texto visible en el nombre accesible (WCAG 2.5.3) y abrir en otra ventana añade
 * `rel="noopener"`.
 *
 * Derivado de PRO Elements (GPL): `modules/posts/skins/skin-base.php`.
 */
abstract class Skin_Base extends \Elementor\Skin_Base {

	/** @var string Enlace de la entrada en curso, guardado antes de pintarla (otros plugins filtran el enlace). */
	protected $current_permalink;

	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- método del núcleo.
		$name = $this->parent->get_name();
		add_action( 'elementor/element/' . $name . '/section_layout/before_section_end', array( $this, 'register_controls' ) );
		add_action( 'elementor/element/' . $name . '/section_query/after_section_end', array( $this, 'register_style_sections' ) );
	}

	public function register_style_sections( Widget_Base $widget ) {
		$this->parent = $widget;

		$this->register_design_controls();
	}

	public function register_controls( Widget_Base $widget ) {
		$this->parent = $widget;

		$this->register_columns_controls();
		$this->register_post_count_control();
		$this->register_thumbnail_controls();
		$this->register_title_controls();
		$this->register_excerpt_controls();
		$this->register_meta_data_controls();
		$this->register_read_more_controls();
		$this->register_link_controls();
	}

	public function register_design_controls() {
		$this->register_design_layout_controls();
		$this->register_design_image_controls();
		$this->register_design_content_controls();
	}

	/** Reglas de la proporción de la imagen (las de PRO más la colocación que PRO hacía con su script). */
	protected static function ratio_selectors(): array {
		return array(
			'{{WRAPPER}} .digi-posts__thumb'     => 'padding-bottom: calc( {{SIZE}} * 100% );',
			'{{WRAPPER}} .digi-posts__thumb img' => 'position: absolute; top: calc(50% + 1px); left: calc(50% + 1px); height: 100%; object-fit: cover; transform: scale(1.01) translate(-50%, -50%);',
		);
	}

	protected function register_thumbnail_controls() {
		$this->add_control(
			'thumbnail',
			array(
				'label'        => esc_html__( 'Posición de la imagen', 'pro-elements' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'top',
				'options'      => array(
					'top'   => esc_html__( 'Arriba', 'pro-elements' ),
					'left'  => esc_html__( 'Izquierda', 'pro-elements' ),
					'right' => esc_html__( 'Derecha', 'pro-elements' ),
					'none'  => esc_html__( 'Ninguna', 'pro-elements' ),
				),
				'prefix_class' => 'digi-posts--thumbnail-',
			)
		);

		$this->add_control(
			'masonry',
			array(
				'label'              => esc_html__( 'Mampostería', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_off'          => esc_html__( 'No', 'pro-elements' ),
				'label_on'           => esc_html__( 'Sí', 'pro-elements' ),
				'condition'          => array(
					$this->get_control_id( 'columns!' ) => '1',
					$this->get_control_id( 'thumbnail' ) => 'top',
				),
				'render_type'        => 'ui',
				'frontend_available' => true,
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'         => 'thumbnail_size',
				'default'      => 'medium',
				'exclude'      => array( 'custom' ),
				'condition'    => array( $this->get_control_id( 'thumbnail!' ) => 'none' ),
				'prefix_class' => 'digi-posts--thumbnail-size-',
			)
		);

		$this->add_responsive_control(
			'item_ratio',
			array(
				'label'          => esc_html__( 'Proporción de la imagen', 'pro-elements' ),
				'type'           => Controls_Manager::SLIDER,
				'default'        => array( 'size' => 0.66 ),
				'tablet_default' => array( 'size' => '' ),
				'mobile_default' => array( 'size' => 0.5 ),
				'range'          => array(
					'px' => array(
						'min'  => 0.1,
						'max'  => 2,
						'step' => 0.01,
					),
				),
				'selectors'      => static::ratio_selectors(),
				'condition'      => array(
					$this->get_control_id( 'thumbnail!' ) => 'none',
					$this->get_control_id( 'masonry' ) => '',
				),
			)
		);

		$this->add_responsive_control(
			'image_width',
			array(
				'label'          => esc_html__( 'Ancho de la imagen', 'pro-elements' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'          => array(
					'%'   => array(
						'min' => 10,
						'max' => 100,
					),
					'px'  => array(
						'min' => 10,
						'max' => 600,
					),
					'em'  => array(
						'min' => 1,
						'max' => 60,
					),
					'rem' => array(
						'min' => 1,
						'max' => 60,
					),
				),
				'default'        => array(
					'size' => 100,
					'unit' => '%',
				),
				'tablet_default' => array(
					'size' => '',
					'unit' => '%',
				),
				'mobile_default' => array(
					'size' => 100,
					'unit' => '%',
				),
				'selectors'      => array( '{{WRAPPER}} .digi-posts__thumb-link' => 'width: {{SIZE}}{{UNIT}};' ),
				'condition'      => array( $this->get_control_id( 'thumbnail!' ) => 'none' ),
			)
		);
	}

	protected function register_columns_controls() {
		$this->add_responsive_control(
			'columns',
			array(
				'label'              => esc_html__( 'Columnas', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => '3',
				'tablet_default'     => '2',
				'mobile_default'     => '1',
				'options'            => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'prefix_class'       => 'elementor-grid%s-',
				'frontend_available' => true,
			)
		);
	}

	protected function register_post_count_control() {
		$this->add_control(
			'posts_per_page',
			array(
				'label'   => esc_html__( 'Entradas por página', 'pro-elements' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 6,
			)
		);
	}

	protected function register_title_controls() {
		$this->add_control(
			'show_title',
			array(
				'label'     => esc_html__( 'Título', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'title_tag',
			array(
				'label'     => esc_html__( 'Etiqueta HTML del título', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
					'p'    => 'p',
				),
				'default'   => 'h3',
				'condition' => array( $this->get_control_id( 'show_title' ) => 'yes' ),
			)
		);
	}

	protected function register_excerpt_controls() {
		$this->add_control(
			'show_excerpt',
			array(
				'label'     => esc_html__( 'Extracto', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
			)
		);

		$this->add_control(
			'excerpt_length',
			array(
				'label'     => esc_html__( 'Longitud del extracto', 'pro-elements' ),
				'type'      => Controls_Manager::NUMBER,
				/** Filtro documentado en wp-includes/formatting.php. */
				'default'   => apply_filters( 'excerpt_length', 25 ),
				'condition' => array( $this->get_control_id( 'show_excerpt' ) => 'yes' ),
			)
		);

		$this->add_control(
			'apply_to_custom_excerpt',
			array(
				'label'     => esc_html__( 'Aplicar al extracto personalizado', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'default'   => 'no',
				'condition' => array( $this->get_control_id( 'show_excerpt' ) => 'yes' ),
			)
		);
	}

	protected function register_read_more_controls() {
		$this->add_control(
			'show_read_more',
			array(
				'label'     => esc_html__( 'Leer más', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'read_more_text',
			array(
				'label'     => esc_html__( 'Texto de «Leer más»', 'pro-elements' ),
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'ai'        => array( 'active' => false ),
				'default'   => esc_html__( 'Leer más »', 'pro-elements' ),
				'condition' => array( $this->get_control_id( 'show_read_more' ) => 'yes' ),
			)
		);

		$this->add_control(
			'read_more_alignment',
			array(
				'label'       => esc_html__( 'Alinear los botones automáticamente', 'pro-elements' ),
				'type'        => Controls_Manager::SWITCHER,
				'label_on'    => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'   => esc_html__( 'No', 'pro-elements' ),
				'default'     => '',
				'render_type' => 'template',
				'selectors'   => array( '{{WRAPPER}}' => '--digi-posts-display: flex; --digi-posts-more-grow: 1;' ),
				'condition'   => array(
					$this->get_control_id( 'masonry!' ) => 'yes',
					$this->get_control_id( 'show_read_more' ) => 'yes',
				),
			)
		);
	}

	protected function register_link_controls() {
		$this->add_control(
			'open_new_tab',
			array(
				'label'       => esc_html__( 'Abrir en una ventana nueva', 'pro-elements' ),
				'type'        => Controls_Manager::SWITCHER,
				'label_on'    => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'   => esc_html__( 'No', 'pro-elements' ),
				'default'     => 'no',
				'render_type' => 'none',
			)
		);
	}

	/** `target` y `rel` de los enlaces de la entrada si se abren en otra ventana. */
	protected function get_optional_link_attributes_html() {
		return 'yes' === $this->get_instance_value( 'open_new_tab' ) ? 'target="_blank" rel="noopener"' : '';
	}

	protected function register_meta_data_controls() {
		$this->add_control(
			'meta_data',
			array(
				'label'       => esc_html__( 'Metadatos', 'pro-elements' ),
				'label_block' => true,
				'type'        => Controls_Manager::SELECT2,
				'default'     => array( 'date', 'comments' ),
				'multiple'    => true,
				'options'     => array(
					'author'   => esc_html__( 'Autor', 'pro-elements' ),
					'date'     => esc_html__( 'Fecha', 'pro-elements' ),
					'time'     => esc_html__( 'Hora', 'pro-elements' ),
					'comments' => esc_html__( 'Comentarios', 'pro-elements' ),
					'modified' => esc_html__( 'Fecha de modificación', 'pro-elements' ),
				),
				'separator'   => 'before',
			)
		);

		$this->add_control(
			'meta_separator',
			array(
				'label'     => esc_html__( 'Separador', 'pro-elements' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '///',
				'ai'        => array( 'active' => false ),
				'selectors' => array( '{{WRAPPER}} .digi-posts__meta span + span:before' => 'content: "{{VALUE}}"' ),
				'condition' => array( $this->get_control_id( 'meta_data!' ) => array() ),
				'dynamic'   => array( 'active' => true ),
			)
		);
	}

	/* ------------------------------------------------------------------ Estilo */

	protected function register_design_layout_controls() {
		$this->start_controls_section(
			'section_design_layout',
			array(
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'column_gap',
			array(
				'label'      => esc_html__( 'Separación entre columnas', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 30 ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array( '{{WRAPPER}}' => '--grid-column-gap: {{SIZE}}{{UNIT}}' ),
			)
		);

		$this->add_responsive_control(
			'row_gap',
			array(
				'label'              => esc_html__( 'Separación entre filas', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', 'em', 'rem', 'custom' ),
				'default'            => array( 'size' => 35 ),
				'range'              => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'frontend_available' => true,
				// `--digi-masonry-gap`: la separación de la mampostería, el número del ajuste en px como en PRO (`loop.js`).
				'selectors'          => array( '{{WRAPPER}}' => '--grid-row-gap: {{SIZE}}{{UNIT}}; --digi-masonry-gap: {{SIZE}}px' ),
			)
		);

		$this->add_control(
			'alignment',
			array(
				'label'        => esc_html__( 'Alineación', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
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
				'prefix_class' => 'digi-posts--align-',
			)
		);

		$this->end_controls_section();
	}

	protected function register_design_image_controls() {
		$this->start_controls_section(
			'section_design_image',
			array(
				'label'     => esc_html__( 'Imagen', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( $this->get_control_id( 'thumbnail!' ) => 'none' ),
			)
		);

		$this->add_responsive_control(
			'img_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__thumb' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				'condition'  => array( $this->get_control_id( 'thumbnail!' ) => 'none' ),
			)
		);

		$this->add_responsive_control(
			'image_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}.digi-posts--thumbnail-left .digi-posts__thumb-link'  => 'margin-right: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.digi-posts--thumbnail-right .digi-posts__thumb-link' => 'margin-left: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.digi-posts--thumbnail-top .digi-posts__thumb-link'   => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
				'default'    => array( 'size' => 20 ),
				'condition'  => array( $this->get_control_id( 'thumbnail!' ) => 'none' ),
			)
		);

		$this->start_controls_tabs( 'thumbnail_effects_tabs' );

		$this->start_controls_tab( 'normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'thumbnail_filters',
				'selector' => '{{WRAPPER}} .digi-posts__thumb img',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'hover', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'thumbnail_hover_filters',
				'selector' => '{{WRAPPER}} .digi-posts__item:hover .digi-posts__thumb img',
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	protected function register_design_content_controls() {
		$this->start_controls_section(
			'section_design_content',
			array(
				'label' => esc_html__( 'Contenido', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_title_style',
			array(
				'label'     => esc_html__( 'Título', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => array( $this->get_control_id( 'show_title' ) => 'yes' ),
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=secondary' ),
				'selectors' => array( '{{WRAPPER}} .digi-posts__title, {{WRAPPER}} .digi-posts__title a' => 'color: {{VALUE}};' ),
				'condition' => array( $this->get_control_id( 'show_title' ) => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'title_typography',
				'global'    => array( 'default' => 'globals/typography?id=primary' ),
				'selector'  => '{{WRAPPER}} .digi-posts__title, {{WRAPPER}} .digi-posts__title a',
				'condition' => array( $this->get_control_id( 'show_title' ) => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name'     => 'text_stroke',
				'selector' => '{{WRAPPER}} .digi-posts__title',
			)
		);

		$this->add_responsive_control(
			'title_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( $this->get_control_id( 'show_title' ) => 'yes' ),
			)
		);

		$this->add_control(
			'heading_meta_style',
			array(
				'label'     => esc_html__( 'Metadatos', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( $this->get_control_id( 'meta_data!' ) => array() ),
			)
		);

		$this->add_control(
			'meta_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__meta' => 'color: {{VALUE}};' ),
				'condition' => array( $this->get_control_id( 'meta_data!' ) => array() ),
			)
		);

		$this->add_control(
			'meta_separator_color',
			array(
				'label'     => esc_html__( 'Color del separador', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__meta span:before' => 'color: {{VALUE}};' ),
				'condition' => array( $this->get_control_id( 'meta_data!' ) => array() ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'meta_typography',
				'global'    => array( 'default' => 'globals/typography?id=secondary' ),
				'selector'  => '{{WRAPPER}} .digi-posts__meta',
				'condition' => array( $this->get_control_id( 'meta_data!' ) => array() ),
			)
		);

		$this->add_responsive_control(
			'meta_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__meta' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( $this->get_control_id( 'meta_data!' ) => array() ),
			)
		);

		$this->add_control(
			'heading_excerpt_style',
			array(
				'label'     => esc_html__( 'Extracto', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( $this->get_control_id( 'show_excerpt' ) => 'yes' ),
			)
		);

		$this->add_control(
			'excerpt_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-posts__excerpt p' => 'color: {{VALUE}};' ),
				'condition' => array( $this->get_control_id( 'show_excerpt' ) => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'excerpt_typography',
				'global'    => array( 'default' => 'globals/typography?id=text' ),
				'selector'  => '{{WRAPPER}} .digi-posts__excerpt p',
				'condition' => array( $this->get_control_id( 'show_excerpt' ) => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'excerpt_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__excerpt' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( $this->get_control_id( 'show_excerpt' ) => 'yes' ),
			)
		);

		$this->add_control(
			'heading_readmore_style',
			array(
				'label'     => esc_html__( 'Leer más', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( $this->get_control_id( 'show_read_more' ) => 'yes' ),
			)
		);

		$this->add_control(
			'read_more_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=accent' ),
				'selectors' => array( '{{WRAPPER}} .digi-posts__more' => 'color: {{VALUE}};' ),
				'condition' => array( $this->get_control_id( 'show_read_more' ) => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'read_more_typography',
				// La `a` da especificidad, como en PRO, para cuando el selector se usa en el CSS de los globales.
				'selector'  => '{{WRAPPER}} a.digi-posts__more',
				'global'    => array( 'default' => 'globals/typography?id=accent' ),
				'condition' => array( $this->get_control_id( 'show_read_more' ) => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'read_more_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-posts__text' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( $this->get_control_id( 'show_read_more' ) => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------ Pintado */

	public function render() {
		$this->parent->query_posts();

		/** @var \WP_Query $query */
		$query = $this->parent->get_query();

		if ( ! $query || ! $query->found_posts ) {
			$this->handle_no_posts_found();
			return;
		}

		$this->render_loop_header();

		// La consulta global, con el bucle ya empezado por el tema.
		if ( $query->in_the_loop ) {
			$this->current_permalink = get_permalink();
			$this->render_post();
		} else {
			while ( $query->have_posts() ) {
				$query->the_post();

				$this->add_render_hooks();
				$this->current_permalink = get_permalink();
				$this->render_post();
				$this->remove_render_hooks();
			}
		}

		wp_reset_postdata();

		$this->render_loop_footer();
	}

	protected function add_render_hooks() {}

	protected function remove_render_hooks() {}

	public function filter_excerpt_length() {
		return $this->get_instance_value( 'excerpt_length' );
	}

	public function filter_excerpt_more( $more ) {
		return '';
	}

	public function get_container_class() {
		return 'digi-posts--skin-' . $this->get_id();
	}

	/** Etiqueta `<img>` de la imagen destacada con `wp_get_attachment_image()` y el tamaño de la piel. */
	protected function get_thumbnail_html(): string {
		$id = (int) get_post_thumbnail_id();
		if ( ! $id || ! wp_attachment_is_image( $id ) ) {
			return '';
		}
		$size  = (string) $this->get_instance_value( 'thumbnail_size_size' );
		$sizes = array_merge( get_intermediate_image_sizes(), array( 'full' ) );
		if ( '' === $size || ! in_array( $size, $sizes, true ) ) {
			$size = 'full';
		}
		return (string) wp_get_attachment_image( $id, $size, false, array( 'class' => "attachment-$size size-$size wp-image-$id" ) );
	}

	/** ¿Se pinta la imagen? En el editor siempre (la piel la oculta con CSS), como PRO. */
	protected function thumbnail_hidden(): bool {
		return 'none' === $this->get_instance_value( 'thumbnail' ) && ! self::is_edit_mode();
	}

	protected static function is_edit_mode(): bool {
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && is_object( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	protected function render_thumbnail() {
		if ( $this->thumbnail_hidden() ) {
			return;
		}

		$html = $this->get_thumbnail_html();

		if ( '' === $html ) {
			return;
		}

		printf(
			'<a class="digi-posts__thumb-link" href="%1$s" tabindex="-1" %2$s><div class="digi-posts__thumb">%3$s</div></a>',
			esc_url( $this->current_permalink ),
			$this->get_optional_link_attributes_html(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal.
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- `wp_get_attachment_image()`.
		);
	}

	protected function render_title() {
		if ( ! $this->get_instance_value( 'show_title' ) ) {
			return;
		}

		$tag = Utils::validate_html_tag( (string) $this->get_instance_value( 'title_tag' ) );

		printf(
			'<%1$s class="digi-posts__title"><a href="%2$s" %3$s>%4$s</a></%1$s>',
			$tag, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta validada.
			esc_url( $this->current_permalink ),
			$this->get_optional_link_attributes_html(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal.
			get_the_title() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- como `the_title()`.
		);
	}

	/** Recorte por palabras de PRO (`Utils::trim_words()`): se aplica al extracto ya filtrado. */
	protected static function trim_words( $text, $length ) {
		if ( ! $length ) {
			return $text;
		}
		$words = preg_split( '/\s+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( count( $words ) > $length ) {
			$words = array_slice( $words, 0, $length );
		}
		return implode( ' ', $words );
	}

	protected function render_excerpt() {
		if ( ! $this->get_instance_value( 'show_excerpt' ) ) {
			return;
		}

		add_filter( 'excerpt_more', array( $this, 'filter_excerpt_more' ), 20 );
		add_filter( 'excerpt_length', array( $this, 'filter_excerpt_length' ), 20 );

		echo '<div class="digi-posts__excerpt">';
		$post = get_post();
		if ( 'yes' === $this->get_instance_value( 'apply_to_custom_excerpt' ) && $post && ! empty( $post->post_excerpt ) ) {
			$excerpt = apply_filters( 'the_excerpt', get_the_excerpt() );
			echo wp_kses_post( self::trim_words( $excerpt, (int) $this->get_instance_value( 'excerpt_length' ) ) );
		} else {
			the_excerpt();
		}
		echo '</div>';

		remove_filter( 'excerpt_length', array( $this, 'filter_excerpt_length' ), 20 );
		remove_filter( 'excerpt_more', array( $this, 'filter_excerpt_more' ), 20 );
	}

	protected function render_read_more() {
		if ( ! $this->get_instance_value( 'show_read_more' ) ) {
			return;
		}

		$settings = $this->parent->get_settings_for_display();
		$text     = (string) ( $settings[ $this->get_control_id( 'read_more_text' ) ] ?? '' );
		$bottom   = $this->display_read_more_bottom();

		if ( $bottom ) {
			echo '<div class="digi-posts__more-wrap">';
		}

		printf(
			'<a class="digi-posts__more" href="%1$s" tabindex="-1" %2$s>%3$s<span class="elementor-screen-only">: %4$s</span></a>',
			esc_url( $this->current_permalink ),
			$this->get_optional_link_attributes_html(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal.
			wp_kses_post( $text ),
			esc_html( wp_strip_all_tags( get_the_title() ) )
		);

		if ( $bottom ) {
			echo '</div>';
		}
	}

	protected function render_post_header() {
		echo '<article class="' . esc_attr( implode( ' ', get_post_class( array( 'digi-posts__item', 'elementor-grid-item' ) ) ) ) . '" role="listitem">';
	}

	protected function render_post_footer() {
		echo '</article>';
	}

	protected function render_text_header() {
		echo '<div class="digi-posts__text">';
	}

	protected function render_text_footer() {
		echo '</div>';
	}

	protected function get_loop_header_widget_classes() {
		return array( 'digi-posts', $this->get_container_class() );
	}

	protected function handle_no_posts_found() {}

	protected function render_loop_header() {
		$classes = $this->get_loop_header_widget_classes();
		$query   = $this->parent->get_query();

		// La rejilla, sólo con entradas (como PRO).
		if ( isset( $query->found_posts ) ) {
			$classes[] = 'elementor-grid';
		}

		$attributes = apply_filters(
			'elementor/skin/loop_header_attributes',
			array(
				'class'           => $classes,
				'role'            => 'list',
				'data-digi-items' => '',
			)
		);
		if ( 'yes' === $this->get_instance_value( 'masonry' ) ) {
			$attributes['data-digi-masonry'] = '';
		}

		$this->parent->add_render_attribute( 'container', $attributes );

		echo '<div ' . $this->parent->get_render_attribute_string( 'container' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados por el núcleo.
	}

	protected function render_loop_footer() {
		echo '</div>';
		$this->parent->render_pagination();
	}

	protected function render_meta_data() {
		$meta = $this->get_instance_value( 'meta_data' );
		if ( empty( $meta ) ) {
			return;
		}
		$meta = (array) $meta;

		echo '<div class="digi-posts__meta">';
		if ( in_array( 'author', $meta, true ) ) {
			$this->render_author();
		}
		if ( in_array( 'date', $meta, true ) ) {
			$this->render_date_by_type();
		}
		if ( in_array( 'time', $meta, true ) ) {
			$this->render_time();
		}
		if ( in_array( 'comments', $meta, true ) ) {
			$this->render_comments();
		}
		if ( in_array( 'modified', $meta, true ) ) {
			$this->render_date_by_type( 'modified' );
		}
		echo '</div>';
	}

	// Cada dato va entre saltos de línea, como en PRO: el separador (`span + span:before`) queda entre espacios.
	protected function render_author() {
		echo "<span class=\"digi-posts__author\">\n" . esc_html( get_the_author() ) . "\n</span>\n";
	}

	protected function render_date_by_type( $type = 'publish' ) {
		$date = 'modified' === $type ? get_the_modified_date() : get_the_date();
		/** Filtro documentado en wp-includes/general-template.php. */
		echo "<span class=\"digi-posts__date\">\n" . apply_filters( 'the_date', $date, get_option( 'date_format' ), '', '' ) . "\n</span>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- la fecha, como PRO.
	}

	protected function render_time() {
		echo "<span class=\"digi-posts__time\">\n" . esc_html( (string) get_the_time() ) . "\n</span>\n";
	}

	protected function render_comments() {
		echo "<span class=\"digi-posts__comments\">\n";
		comments_number();
		echo "\n</span>\n";
	}

	/** «Leer más» al pie de la tarjeta: con la alineación automática, sin mampostería (y nunca en contenido completo). */
	protected function display_read_more_bottom() {
		$settings = $this->parent->get_settings();

		if ( 'full_content' === ( $settings['_skin'] ?? '' ) ) {
			return false;
		}

		return 'yes' === ( $settings[ $this->get_control_id( 'read_more_alignment' ) ] ?? '' )
			&& 'yes' === ( $settings[ $this->get_control_id( 'show_read_more' ) ] ?? '' )
			&& 'yes' !== ( $settings[ $this->get_control_id( 'masonry' ) ] ?? '' );
	}

	protected function render_post() {
		$this->render_post_header();
		$this->render_thumbnail();
		$this->render_text_header();
		$this->render_title();
		$this->render_meta_data();
		$this->render_excerpt();
		$this->render_read_more();
		$this->render_text_footer();
		$this->render_post_footer();
	}
}
