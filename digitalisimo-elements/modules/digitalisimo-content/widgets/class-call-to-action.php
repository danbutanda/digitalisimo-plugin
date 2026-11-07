<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Cards;
use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Llamada a la acción con imagen (`digitalisimo-call-to-action`): sustituye al widget «Call to Action» de PRO
 * (`modules/call-to-action/widgets/call-to-action.php`, mapa `modules/digitalisimo-convert/maps/call-to-action.php`).
 * Tiene un control por cada control de aquél, con el mismo nombre, los mismos valores por defecto y las mismas
 * condiciones; el marcado, las clases y la hoja (`assets/css/call-to-action.css`, sin JavaScript) son propios.
 * Controles derivados de PRO Elements (GPL); textos en español con el dominio `pro-elements`.
 *
 * - Pieles «Clásica» (imagen y contenido) y «Cubierta» (el contenido sobre la imagen). La posición de la imagen por
 *   dispositivo va en variables (`--digi-cta-*`) en lugar de una clase por dispositivo.
 * - La imagen de fondo es una etiqueta `img` (con `srcset` y las decisiones de carga de WordPress) que cubre su caja como
 *   el fondo CSS de PRO; su texto alternativo es el de Medios (PRO lo ponía en un `div` con `role="img"`).
 * - Efectos al pasar el ratón (animación del contenido, de la imagen, superposición y colores) también con el foco del
 *   teclado dentro de la caja (`:focus-within`); con `prefers-reduced-motion` no hay movimiento y el contenido que
 *   entra se muestra desde el principio.
 * - Enlace en toda la caja (`a` raíz, el botón es un `span`) o sólo en el botón; sin enlace, el botón es un `span`.
 * - La cinta es la común de las tarjetas (`Cards::ribbon()`), con su hoja en la del widget.
 * - Sin plantilla JS del editor: el editor pinta lo mismo que el servidor.
 */
class Call_To_Action extends \Elementor\Widget_Base {
	/**
	 * Animaciones del contenido (las de PRO): estado inicial y al pasar el ratón como variables en línea del elemento
	 * (`--digi-cta-a`/`--digi-cta-o` y `--digi-cta-a1`/`--digi-cta-o1`), que la hoja aplica a `transform` y `opacity`.
	 */
	const ANIMATIONS = array(
		'enter-from-right'  => '--digi-cta-a:translateX(1000px);--digi-cta-o:0',
		'enter-from-left'   => '--digi-cta-a:translateX(-1000px);--digi-cta-o:0',
		'enter-from-top'    => '--digi-cta-a:translateY(-600px);--digi-cta-o:0',
		'enter-from-bottom' => '--digi-cta-a:translateY(500px);--digi-cta-o:0',
		'enter-zoom-in'     => '--digi-cta-a:scale(.2);--digi-cta-o:0',
		'enter-zoom-out'    => '--digi-cta-a:scale(2);--digi-cta-o:0',
		'fade-in'           => '--digi-cta-o:0',
		'grow'              => '--digi-cta-a1:scale(1.1)',
		'shrink'            => '--digi-cta-a1:scale(.85)',
		'move-right'        => '--digi-cta-a1:translateX(30px)',
		'move-left'         => '--digi-cta-a1:translateX(-30px)',
		'move-up'           => '--digi-cta-a1:translateY(-30px)',
		'move-down'         => '--digi-cta-a1:translateY(30px)',
		'exit-to-right'     => '--digi-cta-a1:translateX(1000px);--digi-cta-o1:0',
		'exit-to-left'      => '--digi-cta-a1:translateX(-1000px);--digi-cta-o1:0',
		'exit-to-top'       => '--digi-cta-a1:translateY(-600px);--digi-cta-o1:0',
		'exit-to-bottom'    => '--digi-cta-a1:translateY(600px);--digi-cta-o1:0',
		'exit-zoom-in'      => '--digi-cta-a1:scale(2);--digi-cta-o1:0',
		'exit-zoom-out'     => '--digi-cta-a1:scale(.2);--digi-cta-o1:0',
		'fade-out'          => '--digi-cta-o1:0',
	);

	/** Efectos de la imagen (`transformation`): transformación inicial y al pasar el ratón, en línea en la raíz. */
	const TRANSFORMS = array(
		'zoom-in'    => '--digi-cta-b1:scale(1.2)',
		'zoom-out'   => '--digi-cta-b0:scale(1.2);--digi-cta-b1:scale(1)',
		'move-left'  => '--digi-cta-b0:scale(1.2) translateX(8%);--digi-cta-b1:scale(1.2) translateX(-8%)',
		'move-right' => '--digi-cta-b0:scale(1.2) translateX(-8%);--digi-cta-b1:scale(1.2) translateX(8%)',
		'move-up'    => '--digi-cta-b0:scale(1.2) translateY(8%);--digi-cta-b1:scale(1.2) translateY(-8%)',
		'move-down'  => '--digi-cta-b0:scale(1.2) translateY(-8%);--digi-cta-b1:scale(1.2) translateY(8%)',
	);

	public function get_name() {
		return 'digitalisimo-call-to-action';
	}

	public function get_title() {
		return esc_html__( 'Llamada a la acción con imagen', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-image-rollover';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'llamada a la acción', 'call to action', 'cta', 'botón', 'imagen', 'tarjeta', 'cinta' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	/** Como PRO: sin envoltorio interior con el marcado optimizado del núcleo. */
	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_main_image',
			array(
				'label' => esc_html__( 'Imagen', 'pro-elements' ),
			)
		);

		$this->add_control(
			'skin',
			array(
				'label' => esc_html__( 'Piel', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'classic' => esc_html__( 'Clásica', 'pro-elements' ),
					'cover' => esc_html__( 'Cubierta', 'pro-elements' ),
				),
				'render_type' => 'template',
				'prefix_class' => 'digi-cta--skin-',
				'default' => 'classic',
			)
		);

		$this->add_responsive_control(
			'layout',
			array(
				'label' => esc_html__( 'Posición', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-h-align-left',
					),
					'above' => array(
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon' => 'eicon-v-align-top',
					),
					'right' => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-h-align-right',
					),
					'below' => array(
						'title' => esc_html__( 'Debajo', 'pro-elements' ),
						'icon' => 'eicon-v-align-bottom',
					),
				),
				'selectors_dictionary' => array(
					'left'  => '--digi-cta-dir:row;--digi-cta-wrap:nowrap;--digi-cta-bgw:auto;--digi-cta-bgmin:50%;--digi-cta-grow:1',
					'above' => '--digi-cta-dir:column;--digi-cta-wrap:wrap;--digi-cta-bgw:100%;--digi-cta-bgmin:0;--digi-cta-grow:0',
					'right' => '--digi-cta-dir:row-reverse;--digi-cta-wrap:nowrap;--digi-cta-bgw:auto;--digi-cta-bgmin:50%;--digi-cta-grow:1',
					'below' => '--digi-cta-dir:column-reverse;--digi-cta-wrap:wrap;--digi-cta-bgw:100%;--digi-cta-bgmin:0;--digi-cta-grow:0',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta' => '{{VALUE}}',
				),
				'condition' => array(
					'skin!' => 'cover',
				),
			)
		);

		$this->add_control(
			'bg_image',
			array(
				'label' => esc_html__( 'Elegir imagen', 'pro-elements' ),
				'type' => Controls_Manager::MEDIA,
				'dynamic' => array(
					'active' => true,
				),
				'default' => array(
					'url' => Utils::get_placeholder_image_src(),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name' => 'bg_image', // Actually its `image_size`
				'label' => esc_html__( 'Resolución de la imagen', 'pro-elements' ),
				'default' => 'large',
				'condition' => array(
					'bg_image[id]!' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Contenido', 'pro-elements' ),
			)
		);

		$this->add_control(
			'graphic_element',
			array(
				'label' => esc_html__( 'Elemento gráfico', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'none' => array(
						'title' => esc_html__( 'Ninguno', 'pro-elements' ),
						'icon' => 'eicon-ban',
					),
					'image' => array(
						'title' => esc_html__( 'Imagen', 'pro-elements' ),
						'icon' => 'eicon-image-bold',
					),
					'icon' => array(
						'title' => esc_html__( 'Icono', 'pro-elements' ),
						'icon' => 'eicon-star',
					),
				),
				'default' => 'none',
			)
		);

		$this->add_control(
			'graphic_image',
			array(
				'label' => esc_html__( 'Elegir imagen', 'pro-elements' ),
				'type' => Controls_Manager::MEDIA,
				'dynamic' => array(
					'active' => true,
				),
				'default' => array(
					'url' => Utils::get_placeholder_image_src(),
				),
				'condition' => array(
					'graphic_element' => 'image',
				),
				'show_label' => false,
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name' => 'graphic_image', // Actually its `image_size`
				'default' => 'thumbnail',
				'condition' => array(
					'graphic_element' => 'image',
					'graphic_image[id]!' => '',
				),
			)
		);

		$this->add_control(
			'selected_icon',
			array(
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'default' => array(
					'value' => 'fas fa-star',
					'library' => 'fa-solid',
				),
				'condition' => array(
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'title',
			array(
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => array(
					'active' => true,
				),
				'default' => esc_html__( 'Este es el título', 'pro-elements' ),
				'placeholder' => esc_html__( 'Escribe el título', 'pro-elements' ),
				'label_block' => true,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'title_tag',
			array(
				'label' => esc_html__( 'Etiqueta HTML del título', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
					'div' => 'div',
					'span' => 'span',
				),
				'default' => 'h2',
				'condition' => array(
					'title!' => '',
				),
			)
		);

		$this->add_control(
			'description',
			array(
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
				'type' => Controls_Manager::TEXTAREA,
				'dynamic' => array(
					'active' => true,
				),
				'default' => esc_html__( 'Escribe aquí una descripción breve que acompañe al título.', 'pro-elements' ),
				'placeholder' => esc_html__( 'Escribe la descripción', 'pro-elements' ),
				'separator' => 'before',
				'rows' => 5,
			)
		);

		$this->add_control(
			'description_tag',
			array(
				'label' => esc_html__( 'Etiqueta HTML de la descripción', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
					'div' => 'div',
					'span' => 'span',
				),
				'default' => 'div',
				'condition' => array(
					'description!' => '',
				),
			)
		);

		$this->add_control(
			'button',
			array(
				'label' => esc_html__( 'Texto del botón', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => array(
					'active' => true,
				),
				'default' => esc_html__( 'Haz clic aquí', 'pro-elements' ),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'link',
			array(
				'label' => esc_html__( 'Enlace', 'pro-elements' ),
				'type' => Controls_Manager::URL,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'link_click',
			array(
				'label' => esc_html__( 'Aplicar el enlace en', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'box' => esc_html__( 'Toda la caja', 'pro-elements' ),
					'button' => esc_html__( 'Sólo el botón', 'pro-elements' ),
				),
				'default' => 'button',
				'condition' => array(
					'link[url]!' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_ribbon',
			array(
				'label' => esc_html__( 'Cinta', 'pro-elements' ),
			)
		);

		$this->add_control(
			'ribbon_title',
			array(
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'ribbon_horizontal_position',
			array(
				'label' => esc_html__( 'Posición', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-h-align-left',
					),
					'right' => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-h-align-right',
					),
				),
				'condition' => array(
					'ribbon_title!' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'box_style',
			array(
				'label' => esc_html__( 'Caja', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'min-height',
			array(
				'label' => esc_html__( 'Altura', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range' => array(
					'px' => array(
						'min' => 100,
						'max' => 1000,
					),
					'em' => array(
						'min' => 10,
						'max' => 100,
					),
					'rem' => array(
						'min' => 10,
						'max' => 100,
					),
					'vh' => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__content' => 'min-height: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'alignment',
			array(
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'start' => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					),
					'end' => array(
						'title' => esc_html__( 'Fin', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					),
				),
				'default' => 'center',
				'classes' => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left' => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__content' => 'text-align: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'vertical_position',
			array(
				'label' => esc_html__( 'Posición vertical', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'top' => array(
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon' => 'eicon-v-align-top',
					),
					'middle' => array(
						'title' => esc_html__( 'En medio', 'pro-elements' ),
						'icon' => 'eicon-v-align-middle',
					),
					'bottom' => array(
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon' => 'eicon-v-align-bottom',
					),
				),
				'prefix_class' => 'digi-cta--valign-',
			)
		);

		$this->add_responsive_control(
			'padding',
			array(
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'heading_bg_image_style',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Imagen', 'pro-elements' ),
				'condition' => array(
					'bg_image[url]!' => '',
					'skin' => 'classic',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'image_min_width',
			array(
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 500,
					),
					'em' => array(
						'max' => 50,
					),
					'rem' => array(
						'max' => 50,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__bg-wrapper' => 'min-width: {{SIZE}}{{UNIT}}',
				),
				'condition' => array(
					'skin' => 'classic',
					'layout!' => 'above',
				),
			)
		);

		$this->add_responsive_control(
			'image_min_height',
			array(
				'label' => esc_html__( 'Altura', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 500,
					),
					'em' => array(
						'max' => 50,
					),
					'rem' => array(
						'max' => 50,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__bg-wrapper' => 'min-height: {{SIZE}}{{UNIT}}',
				),
				'condition' => array(
					'skin' => 'classic',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'graphic_element_style',
			array(
				'label' => esc_html__( 'Elemento gráfico', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'graphic_element!' => array(
						'none',
						'',
					),
				),
			)
		);

		$this->add_control(
			'graphic_image_spacing',
			array(
				'label' => esc_html__( 'Espacio', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 100,
					),
					'em' => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__image' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_control(
			'graphic_image_width',
			array(
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default' => array(
					'unit' => '%',
				),
				'range' => array(
					'%' => array(
						'min' => 5,
						'max' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__image img' => 'width: {{SIZE}}{{UNIT}}',
				),
				'condition' => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'graphic_image_border',
				'selector' => '{{WRAPPER}} .digi-cta__image img',
				'condition' => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_control(
			'graphic_image_border_radius',
			array(
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 200,
					),
					'em' => array(
						'max' => 20,
					),
					'rem' => array(
						'max' => 20,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__image img' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
				'condition' => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_control(
			'icon_view',
			array(
				'label' => esc_html__( 'Vista', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'default' => esc_html__( 'Predeterminada', 'pro-elements' ),
					'stacked' => esc_html__( 'Apilado', 'pro-elements' ),
					'framed' => esc_html__( 'Enmarcado', 'pro-elements' ),
				),
				'default' => 'default',
				'condition' => array(
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'icon_shape',
			array(
				'label' => esc_html__( 'Forma', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'circle' => esc_html__( 'Círculo', 'pro-elements' ),
					'square' => esc_html__( 'Cuadrado', 'pro-elements' ),
				),
				'default' => 'circle',
				'condition' => array(
					'icon_view!' => 'default',
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'icon_spacing',
			array(
				'label' => esc_html__( 'Espacio', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 100,
					),
					'em' => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon-wrapper' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'icon_primary_color',
			array(
				'label' => esc_html__( 'Color principal', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .elementor-view-stacked .elementor-icon' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .elementor-view-stacked .elementor-icon svg' => 'stroke: {{VALUE}}',
					'{{WRAPPER}} .elementor-view-framed .elementor-icon, {{WRAPPER}} .elementor-view-default .elementor-icon' => 'color: {{VALUE}}; border-color: {{VALUE}}',
					'{{WRAPPER}} .elementor-view-framed .elementor-icon, {{WRAPPER}} .elementor-view-default .elementor-icon svg' => 'fill: {{VALUE}};',
				),
				'condition' => array(
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'icon_secondary_color',
			array(
				'label' => esc_html__( 'Color secundario', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'condition' => array(
					'graphic_element' => 'icon',
					'icon_view!' => 'default',
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-view-framed .elementor-icon' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-view-framed .elementor-icon svg' => 'stroke: {{VALUE}};',
					'{{WRAPPER}} .elementor-view-stacked .elementor-icon' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-view-stacked .elementor-icon svg' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'icon_size',
			array(
				'label' => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'min' => 6,
						'max' => 300,
					),
					'em' => array(
						'min' => 0.6,
						'max' => 30,
					),
					'rem' => array(
						'min' => 0.6,
						'max' => 30,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon' => 'font-size: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'graphic_element' => 'icon',
				),
			)
		);

		$this->add_control(
			'icon_padding',
			array(
				'label' => esc_html__( 'Relleno del icono', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon' => 'padding: {{SIZE}}{{UNIT}};',
				),
				'range' => array(
					'px' => array(
						'max' => 50,
					),
					'em' => array(
						'min' => 0,
						'max' => 5,
					),
					'rem' => array(
						'min' => 0,
						'max' => 5,
					),
				),
				'condition' => array(
					'graphic_element' => 'icon',
					'icon_view!' => 'default',
				),
			)
		);

		$this->add_control(
			'icon_border_width',
			array(
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 20,
					),
					'em' => array(
						'max' => 2,
					),
					'rem' => array(
						'max' => 2,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon' => 'border-width: {{SIZE}}{{UNIT}}',
				),
				'condition' => array(
					'graphic_element' => 'icon',
					'icon_view' => 'framed',
				),
			)
		);

		$this->add_control(
			'icon_border_radius',
			array(
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition' => array(
					'graphic_element' => 'icon',
					'icon_view!' => 'default',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_content_style',
			array(
				'label' => esc_html__( 'Contenido', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'conditions' => array(
					'relation' => 'or',
					'terms' => array(
						array(
							'name' => 'title',
							'operator' => '!==',
							'value' => '',
						),
						array(
							'name' => 'description',
							'operator' => '!==',
							'value' => '',
						),
					),
				),
			)
		);

		$this->add_control(
			'heading_style_title',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'condition' => array(
					'title!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'title_typography',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .digi-cta__title',
				'condition' => array(
					'title!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name' => 'text_stroke',
				'selector' => '{{WRAPPER}} .digi-cta__title',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name' => 'title_text_shadow',
				'selector' => '{{WRAPPER}} .digi-cta__title',
			)
		);

		$this->add_responsive_control(
			'title_spacing',
			array(
				'label' => esc_html__( 'Espacio', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__title:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'title!' => '',
				),
			)
		);

		$this->add_control(
			'heading_style_description',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
				'separator' => 'before',
				'condition' => array(
					'description!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'description_typography',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'selector' => '{{WRAPPER}} .digi-cta__description',
				'condition' => array(
					'description!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'description_spacing',
			array(
				'label' => esc_html__( 'Espacio', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__description:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'description!' => '',
				),
			)
		);

		$this->add_control(
			'heading_content_colors',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Colores', 'pro-elements' ),
				'separator' => 'before',
			)
		);

		$this->start_controls_tabs( 'color_tabs' );

		$this->start_controls_tab( 'colors_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'content_bg_color',
			array(
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__content' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'skin' => 'classic',
				),
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label' => esc_html__( 'Color del título', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__title' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'title!' => '',
				),
			)
		);

		$this->add_control(
			'description_color',
			array(
				'label' => esc_html__( 'Color de la descripción', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__description' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'description!' => '',
				),
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label' => esc_html__( 'Color del botón', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__button' => 'color: {{VALUE}}; border-color: {{VALUE}}',
				),
				'condition' => array(
					'button!' => '',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'colors_hover',
			array(
				'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
			)
		);

		$this->add_control(
			'content_bg_color_hover',
			array(
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta:hover .digi-cta__content, {{WRAPPER}} .digi-cta:focus-within .digi-cta__content' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'skin' => 'classic',
				),
			)
		);

		$this->add_control(
			'title_color_hover',
			array(
				'label' => esc_html__( 'Color del título', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta:hover .digi-cta__title, {{WRAPPER}} .digi-cta:focus-within .digi-cta__title' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'title!' => '',
				),
			)
		);

		$this->add_control(
			'description_color_hover',
			array(
				'label' => esc_html__( 'Color de la descripción', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta:hover .digi-cta__description, {{WRAPPER}} .digi-cta:focus-within .digi-cta__description' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'description!' => '',
				),
			)
		);

		$this->add_control(
			'button_color_hover',
			array(
				'label' => esc_html__( 'Color del botón', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta:hover .digi-cta__button, {{WRAPPER}} .digi-cta:focus-within .digi-cta__button' => 'color: {{VALUE}}; border-color: {{VALUE}}',
				),
				'condition' => array(
					'button!' => '',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'button_style',
			array(
				'label' => esc_html__( 'Botón', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'button!' => '',
				),
			)
		);

		$this->add_control(
			'button_size',
			array(
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'xs' => esc_html__( 'Extra pequeño', 'pro-elements' ),
					'sm' => esc_html__( 'Pequeño', 'pro-elements' ),
					'md' => esc_html__( 'Mediano', 'pro-elements' ),
					'lg' => esc_html__( 'Grande', 'pro-elements' ),
					'xl' => esc_html__( 'Extra grande', 'pro-elements' ),
				),
				'default' => 'sm',
				'condition' => array(
					'button_size!' => 'sm', // a workaround to hide the control, unless it's in use (not default).
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'button_typography',
				'selector' => '{{WRAPPER}} .digi-cta__button',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name' => 'button_text_shadow',
				'selector' => '{{WRAPPER}} .digi-cta__button',
			)
		);

		$this->start_controls_tabs( 'button_tabs' );

		$this->start_controls_tab( 'button_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__button' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_background_color',
			array(
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__button' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_border_color',
			array(
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__button' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'button-hover',
			array(
				'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
			)
		);

		$this->add_control(
			'button_hover_text_color',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__button:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_background_color',
			array(
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_border_color',
			array(
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__button:hover' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'button_border_width',
			array(
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 20,
					),
					'em' => array(
						'max' => 2,
					),
					'rem' => array(
						'max' => 2,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__button' => 'border-width: {{SIZE}}{{UNIT}};',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 100,
					),
					'em' => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__button' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'button_box_shadow',
				'selector' => '{{WRAPPER}} .digi-cta__button',
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator' => 'before',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_ribbon_style',
			array(
				'label' => esc_html__( 'Cinta', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'show_label' => false,
				'condition' => array(
					'ribbon_title!' => '',
				),
			)
		);

		$this->add_control(
			'ribbon_bg_color',
			array(
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-ribbon__inner' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'ribbon_text_color',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-ribbon__inner' => 'color: {{VALUE}}',
				),
			)
		);

		$ribbon_distance_transform = is_rtl() ? 'translateY(-50%) translateX({{SIZE}}{{UNIT}}) rotate(-45deg)' : 'translateY(-50%) translateX(-50%) translateX({{SIZE}}{{UNIT}}) rotate(-45deg)';

		$this->add_responsive_control(
			'ribbon_distance',
			array(
				'label' => esc_html__( 'Distancia', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 50,
					),
					'em' => array(
						'max' => 5,
					),
					'em' => array(
						'max' => 5,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-ribbon__inner' => 'margin-top: {{SIZE}}{{UNIT}}; transform: ' . $ribbon_distance_transform,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'ribbon_typography',
				'selector' => '{{WRAPPER}} .digi-ribbon__inner',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'box_shadow',
				'selector' => '{{WRAPPER}} .digi-ribbon__inner',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'hover_effects',
			array(
				'label' => esc_html__( 'Efectos al pasar el ratón', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'content_hover_heading',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Contenido', 'pro-elements' ),
				'condition' => array(
					'skin' => 'cover',
				),
			)
		);

		$this->add_control(
			'content_animation',
			array(
				'label' => esc_html__( 'Animación al pasar el ratón', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'groups' => array(
					array(
						'label' => esc_html__( 'Ninguno', 'pro-elements' ),
						'options' => array(
							'' => esc_html__( 'Ninguno', 'pro-elements' ),
						),
					),
					array(
						'label' => esc_html__( 'Entrada', 'pro-elements' ),
						'options' => array(
							'enter-from-right' => esc_html__( 'Entrar desde la derecha', 'pro-elements' ),
							'enter-from-left' => esc_html__( 'Entrar desde la izquierda', 'pro-elements' ),
							'enter-from-top' => esc_html__( 'Entrar desde arriba', 'pro-elements' ),
							'enter-from-bottom' => esc_html__( 'Entrar desde abajo', 'pro-elements' ),
							'enter-zoom-in' => esc_html__( 'Acercar', 'pro-elements' ),
							'enter-zoom-out' => esc_html__( 'Alejar', 'pro-elements' ),
							'fade-in' => esc_html__( 'Aparecer', 'pro-elements' ),
						),
					),
					array(
						'label' => esc_html__( 'Reacción', 'pro-elements' ),
						'options' => array(
							'grow' => esc_html__( 'Crecer', 'pro-elements' ),
							'shrink' => esc_html__( 'Encoger', 'pro-elements' ),
							'move-right' => esc_html__( 'Mover a la derecha', 'pro-elements' ),
							'move-left' => esc_html__( 'Mover a la izquierda', 'pro-elements' ),
							'move-up' => esc_html__( 'Mover arriba', 'pro-elements' ),
							'move-down' => esc_html__( 'Mover abajo', 'pro-elements' ),
						),
					),
					array(
						'label' => esc_html__( 'Salida', 'pro-elements' ),
						'options' => array(
							'exit-to-right' => esc_html__( 'Salir hacia la derecha', 'pro-elements' ),
							'exit-to-left' => esc_html__( 'Salir hacia la izquierda', 'pro-elements' ),
							'exit-to-top' => esc_html__( 'Salir hacia arriba', 'pro-elements' ),
							'exit-to-bottom' => esc_html__( 'Salir hacia abajo', 'pro-elements' ),
							'exit-zoom-in' => esc_html__( 'Acercar', 'pro-elements' ),
							'exit-zoom-out' => esc_html__( 'Alejar', 'pro-elements' ),
							'fade-out' => esc_html__( 'Desvanecer', 'pro-elements' ),
						),
					),
				),
				'default' => 'grow',
				'condition' => array(
					'skin' => 'cover',
				),
			)
		);

		// Clase `digi-cta--animated-content` en el widget cuando hay animación del contenido (como PRO).
		$this->add_control(
			'animation_class',
			array(
				'label' => esc_html__( 'Animación', 'pro-elements' ),
				'type' => Controls_Manager::HIDDEN,
				'default' => 'animated-content',
				'prefix_class' => 'digi-cta--',
				'condition' => array(
					'content_animation!' => '',
				),
			)
		);

		$this->add_control(
			'content_animation_duration',
			array(
				'label' => esc_html__( 'Duración de la animación', 'pro-elements' ) . ' (ms)',
				'type' => Controls_Manager::SLIDER,
				'render_type' => 'template',
				'default' => array(
					'size' => 1000,
				),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 3000,
						'step' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__content-item' => 'transition-duration: {{SIZE}}ms',
					'{{WRAPPER}}.digi-cta--sequenced-animation .digi-cta__content-item:nth-child(2)' => 'transition-delay: calc( {{SIZE}}ms / 3 )',
					'{{WRAPPER}}.digi-cta--sequenced-animation .digi-cta__content-item:nth-child(3)' => 'transition-delay: calc( ( {{SIZE}}ms / 3 ) * 2 )',
					'{{WRAPPER}}.digi-cta--sequenced-animation .digi-cta__content-item:nth-child(4)' => 'transition-delay: calc( ( {{SIZE}}ms / 3 ) * 3 )',
				),
				'condition' => array(
					'content_animation!' => '',
					'skin' => 'cover',
				),
			)
		);

		$this->add_control(
			'sequenced_animation',
			array(
				'label' => esc_html__( 'Animación en secuencia', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'digi-cta--sequenced-animation',
				'prefix_class' => '',
				'condition' => array(
					'content_animation!' => '',
					'skin' => 'cover',
				),
			)
		);

		$this->add_control(
			'background_hover_heading',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Fondo', 'pro-elements' ),
				'separator' => 'before',
				'condition' => array(
					'skin' => 'cover',
				),
			)
		);

		$this->add_control(
			'transformation',
			array(
				'label' => esc_html__( 'Animación al pasar el ratón', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'' => esc_html__( 'Ninguno', 'pro-elements' ),
					'zoom-in' => esc_html__( 'Acercar', 'pro-elements' ),
					'zoom-out' => esc_html__( 'Alejar', 'pro-elements' ),
					'move-left' => esc_html__( 'Mover a la izquierda', 'pro-elements' ),
					'move-right' => esc_html__( 'Mover a la derecha', 'pro-elements' ),
					'move-up' => esc_html__( 'Mover arriba', 'pro-elements' ),
					'move-down' => esc_html__( 'Mover abajo', 'pro-elements' ),
				),
				'default' => 'zoom-in',
				'prefix_class' => 'digi-cta--bg-',
			)
		);

		$this->start_controls_tabs( 'bg_effects_tabs' );

		$this->start_controls_tab( 'normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'overlay_color',
			array(
				'label' => esc_html__( 'Color de la superposición', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta:not(:hover):not(:focus-within) .digi-cta__bg-overlay' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name' => 'bg_filters',
				'selector' => '{{WRAPPER}} .digi-cta__bg',
			)
		);

		$this->add_control(
			'overlay_blend_mode',
			array(
				'label' => esc_html__( 'Modo de mezcla', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'' => esc_html__( 'Normal', 'pro-elements' ),
					'multiply' => esc_html__( 'Multiplicar', 'pro-elements' ),
					'screen' => esc_html__( 'Trama', 'pro-elements' ),
					'overlay' => esc_html__( 'Superponer', 'pro-elements' ),
					'darken' => esc_html__( 'Oscurecer', 'pro-elements' ),
					'lighten' => esc_html__( 'Aclarar', 'pro-elements' ),
					'color-dodge' => esc_html__( 'Sobreexponer color', 'pro-elements' ),
					'color-burn' => esc_html__( 'Subexponer color', 'pro-elements' ),
					'hue' => esc_html__( 'Tono', 'pro-elements' ),
					'saturation' => esc_html__( 'Saturación', 'pro-elements' ),
					'color' => esc_html__( 'Color', 'pro-elements' ),
					'exclusion' => esc_html__( 'Exclusión', 'pro-elements' ),
					'luminosity' => esc_html__( 'Luminosidad', 'pro-elements' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta__bg-overlay' => 'mix-blend-mode: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'hover',
			array(
				'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
			)
		);

		$this->add_control(
			'overlay_color_hover',
			array(
				'label' => esc_html__( 'Color de la superposición', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-cta:hover .digi-cta__bg-overlay, {{WRAPPER}} .digi-cta:focus-within .digi-cta__bg-overlay' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name' => 'bg_filters_hover',
				'selector' => '{{WRAPPER}} .digi-cta:hover .digi-cta__bg, {{WRAPPER}} .digi-cta:focus-within .digi-cta__bg',
			)
		);

		$this->add_control(
			'effect_duration',
			array(
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ) . ' (ms)',
				'type' => Controls_Manager::SLIDER,
				'render_type' => 'template',
				'default' => array(
					'size' => 1500,
				),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 3000,
						'step' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-cta .digi-cta__bg, {{WRAPPER}} .digi-cta .digi-cta__bg-overlay' => 'transition-duration: {{SIZE}}ms',
				),
				'separator' => 'before',
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
		}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$skin     = 'cover' === ( $settings['skin'] ?? 'classic' ) ? 'cover' : 'classic';
		$graphic  = (string) ( $settings['graphic_element'] ?? 'none' );
		$bg       = (array) ( $settings['bg_image'] ?? array() );
		$has_bg   = '' !== (string) ( $bg['url'] ?? '' ) || ! empty( $bg['id'] );
		$title    = (string) ( $settings['title'] ?? '' );
		$desc     = (string) ( $settings['description'] ?? '' );
		$button   = (string) ( $settings['button'] ?? '' );
		$link     = Cards::link( $settings['link'] ?? array() );
		$box      = $link && 'box' === ( $settings['link_click'] ?? 'button' );
		$anim     = (string) ( $settings['content_animation'] ?? '' );
		$anim     = 'cover' === $skin && isset( self::ANIMATIONS[ $anim ] ) ? $anim : '';
		// Atributos de cada elemento del contenido: su clase propia más la común y, con animación, su clase y sus variables;
		// con `$key`, la edición en línea del lienzo de ese texto (como PRO: título, descripción y botón).
		$item = static function ( string $class, string $key = '' ) use ( $anim ): string {
			$attrs = array( 'class' => $class . ' digi-cta__content-item' . ( '' !== $anim ? ' digi-cta__item--' . $anim : '' ) );
			if ( '' !== $anim ) {
				$attrs['style'] = self::ANIMATIONS[ $anim ];
			}
			return Cards::attributes( '' === $key ? $attrs : Cards::inline( $attrs, $key ) );
		};

		$graphic_html = '';
		if ( 'image' === $graphic && ( '' !== (string) ( $settings['graphic_image']['url'] ?? '' ) || ! empty( $settings['graphic_image']['id'] ) ) ) {
			$img = Cards::image( (array) $settings['graphic_image'], $settings, 'graphic_image' );
			if ( '' !== $img ) {
				$graphic_html = '<div ' . $item( 'digi-cta__image' ) . '>' . $img . '</div>';
			}
		} elseif ( 'icon' === $graphic ) {
			$icon = Cards::icon( $settings, 'selected_icon' );
			if ( '' !== $icon ) {
				$view  = in_array( $settings['icon_view'] ?? 'default', array( 'default', 'stacked', 'framed' ), true ) ? (string) ( $settings['icon_view'] ?? 'default' ) : 'default';
				$class = 'elementor-icon-wrapper digi-cta__icon elementor-view-' . $view;
				if ( 'default' !== $view ) {
					$class .= ' elementor-shape-' . ( 'square' === ( $settings['icon_shape'] ?? 'circle' ) ? 'square' : 'circle' );
				}
				$graphic_html = '<div ' . $item( $class ) . '><div class="elementor-icon">' . $icon . '</div></div>';
			}
		}
		$print_content = '' !== $title || '' !== $desc || '' !== $button || 'none' !== $graphic;

		$transform = (string) ( $settings['transformation'] ?? '' );
		$root      = array( 'class' => 'digi-cta', 'style' => self::TRANSFORMS[ $transform ] ?? null );
		if ( $box ) {
			$root += $link;
		}
		$tag = $box ? 'a' : 'div';
		echo '<' . $tag . ' ' . Cards::attributes( $root ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados en Cards::attributes().
		if ( $has_bg || 'classic' !== $skin ) {
			$img = $has_bg ? Cards::image( $bg, $settings, 'bg_image', array( 'class' => 'digi-cta__bg-img' ) ) : '';
			echo '<div class="digi-cta__bg-wrapper"><div class="digi-cta__bg">' . $img . '</div><div class="digi-cta__bg-overlay"></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- imagen de Image_Markup.
		}
		if ( $print_content ) {
			echo '<div class="digi-cta__content">';
			echo $graphic_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- imagen e icono generados arriba.
			if ( '' !== $title ) {
				$title_tag = Cards::tag( $settings['title_tag'] ?? 'h2', 'h2' );
				echo '<' . $title_tag . ' ' . $item( 'digi-cta__title', 'title' ) . '>' . wp_kses_post( $title ) . '</' . $title_tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de la lista permitida.
			}
			if ( '' !== $desc ) {
				$desc_tag = Cards::tag( $settings['description_tag'] ?? 'div', 'div' );
				echo '<' . $desc_tag . ' ' . $item( 'digi-cta__description', 'description' ) . '>' . wp_kses_post( $desc ) . '</' . $desc_tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de la lista permitida.
			}
			if ( '' !== $button ) {
				$attrs = array( 'class' => 'digi-cta__button elementor-button elementor-size-' . Cards::button_size( $settings['button_size'] ?? 'sm', 'sm' ) );
				$btag  = $link && ! $box ? 'a' : 'span';
				if ( 'a' === $btag ) {
					$attrs += $link;
				}
				echo '<div ' . $item( 'digi-cta__button-wrapper' ) . '><' . $btag . ' ' . Cards::attributes( Cards::inline( $attrs, 'button' ) ) . '>' . wp_kses_post( $button ) . '</' . $btag . '></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados.
			}
			echo '</div>';
		}
		echo Cards::ribbon( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- texto con wp_kses_post().
		echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- div o a.
	}

	/** Markdown como el de PRO: imagen de fondo, imagen gráfica, título con su nivel, descripción y botón. */
	public function render_markdown(): string {
		$settings = $this->get_settings_for_display();
		$blocks   = array();
		$bg       = Cards::markdown_image( (array) ( $settings['bg_image'] ?? array() ) );
		if ( '' !== $bg ) {
			$blocks[] = $bg;
		}
		if ( 'image' === ( $settings['graphic_element'] ?? '' ) ) {
			$graphic = Cards::markdown_image( (array) ( $settings['graphic_image'] ?? array() ) );
			if ( '' !== $graphic ) {
				$blocks[] = $graphic;
			}
		}
		$content = array();
		$title   = Cards::plain( $settings['title'] ?? '' );
		$desc    = Cards::plain( $settings['description'] ?? '' );
		$button  = Cards::plain( $settings['button'] ?? '' );
		if ( '' !== $title ) {
			$content[] = Cards::markdown_heading( $title, (string) ( $settings['title_tag'] ?? 'h2' ) );
		}
		if ( '' !== $desc ) {
			$content[] = $desc;
		}
		if ( '' !== $button ) {
			$content[] = Cards::markdown_button( $button, (string) ( $settings['link']['url'] ?? '' ) );
		}
		if ( $content ) {
			$blocks[] = implode( "\n\n", $content );
		}
		return implode( "\n\n", $blocks );
	}
}
