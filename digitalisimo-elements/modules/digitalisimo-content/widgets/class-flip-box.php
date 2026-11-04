<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Cards;
use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Tarjeta giratoria (`digitalisimo-flip-box`): sustituye al widget «Flip Box» de PRO
 * (`modules/flip-box/widgets/flip-box.php`, mapa `modules/digitalisimo-convert/maps/flip-box.php`). Tiene un control por
 * cada control de aquél, con el mismo nombre, los mismos valores por defecto y las mismas condiciones; el marcado, las
 * clases y la hoja (`assets/css/flip-box.css`, sin JavaScript en el sitio) son propios. Controles derivados de PRO
 * Elements (GPL); textos en español con el dominio `pro-elements`.
 *
 * - Anverso (imagen o icono, título y descripción) y reverso (título, descripción y botón, con el enlace en el botón o
 *   en todo el reverso). Efectos giro (con profundidad 3D opcional), deslizar, empujar, acercar, alejar y fundido, con
 *   su dirección; las transformaciones de la dirección van en variables en línea (`--digi-fb-*`, `DIRECTIONS`).
 * - Gira al pasar el ratón y con el foco del teclado dentro (`:focus-within`): con un enlace en el reverso, al llegar a
 *   él; sin enlace, la tarjeta es un grupo enfocable (`tabindex="0"`, con el título del anverso como nombre) para que
 *   el reverso se pueda mostrar sin ratón. Sin enlace, el botón es un `span` (PRO pintaba un falso botón enfocable que
 *   no hacía nada). Con `prefers-reduced-motion` el cambio de cara es inmediato.
 * - En el editor, al abrir las secciones del reverso se muestra el reverso (`assets/js/flip-box-editor.js`, clase
 *   `digi-flip-box--flipped`), como en PRO.
 * - Sin plantilla JS del editor: el editor pinta lo mismo que el servidor.
 */
class Flip_Box extends \Elementor\Widget_Base {
	/**
	 * Transformaciones de cada dirección, en línea en la raíz: el anverso al girar (`--digi-fb-f`) y el reverso en reposo
	 * (`--digi-fb-b`) del giro, y el anverso al empujar (`--digi-fb-p`) y el reverso en reposo de empujar y deslizar
	 * (`--digi-fb-q`). Los valores son los de PRO.
	 */
	const DIRECTIONS = array(
		'right' => '--digi-fb-f:rotateX(0) rotateY(180deg);--digi-fb-b:rotateX(0) rotateY(-180deg);--digi-fb-p:translateX(100%) translateY(0);--digi-fb-q:translateX(-100%) translateY(0)',
		'left'  => '--digi-fb-f:rotateX(0) rotateY(-180deg);--digi-fb-b:rotateX(0) rotateY(180deg);--digi-fb-p:translateX(-100%) translateY(0);--digi-fb-q:translateX(100%) translateY(0)',
		'up'    => '--digi-fb-f:rotateX(180deg) rotateY(0);--digi-fb-b:rotateX(-180deg) rotateY(0);--digi-fb-p:translateX(0) translateY(-100%);--digi-fb-q:translateX(0) translateY(100%)',
		'down'  => '--digi-fb-f:rotateX(-180deg) rotateY(0);--digi-fb-b:rotateX(180deg) rotateY(0);--digi-fb-p:translateX(0) translateY(100%);--digi-fb-q:translateX(0) translateY(-100%)',
	);

	public function get_name() {
		return 'digitalisimo-flip-box';
	}

	public function get_title() {
		return esc_html__( 'Tarjeta giratoria', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-flip-box';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'tarjeta giratoria', 'flip box', 'girar', 'voltear', 'anverso', 'reverso', 'tarjeta' );
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

	protected function register_controls() {


		$this->start_controls_section(
			'section_side_a_content',
			array(
				'label' => esc_html__( 'Anverso', 'pro-elements' ),
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
				'default' => 'icon',
			)
		);

		$this->add_control(
			'image',
			array(
				'label' => esc_html__( 'Elegir imagen', 'pro-elements' ),
				'type' => Controls_Manager::MEDIA,
				'default' => array(
					'url' => Utils::get_placeholder_image_src(),
				),
				'dynamic' => array(
					'active' => true,
				),
				'condition' => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name' => 'image', // Actually its `image_size`
				'default' => 'thumbnail',
				'condition' => array(
					'graphic_element' => 'image',
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
			'title_text_a',
			array(
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Este es el título', 'pro-elements' ),
				'placeholder' => esc_html__( 'Escribe el título', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
				'label_block' => true,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'description_text_a',
			array(
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => esc_html__( 'Escribe aquí una descripción breve que acompañe al título.', 'pro-elements' ),
				'placeholder' => esc_html__( 'Escribe la descripción', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
				'rows' => 10,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_side_b_content',
			array(
				'label' => esc_html__( 'Reverso', 'pro-elements' ),
			)
		);

		$this->add_control(
			'title_text_b',
			array(
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Este es el título', 'pro-elements' ),
				'placeholder' => esc_html__( 'Escribe el título', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
				'label_block' => true,
			)
		);

		$this->add_control(
			'description_text_b',
			array(
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => esc_html__( 'Escribe aquí una descripción breve que acompañe al título.', 'pro-elements' ),
				'placeholder' => esc_html__( 'Escribe la descripción', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
				'rows' => 10,
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label' => esc_html__( 'Texto del botón', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Haz clic aquí', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
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
			'section_box_settings',
			array(
				'label' => esc_html__( 'Ajustes', 'pro-elements' ),
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
					'p' => 'p',
				),
				'default' => 'h3',
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
					'p' => 'p',
				),
				'default' => 'div',
			)
		);

		$this->add_responsive_control(
			'height',
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
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'border_radius',
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
				'separator' => 'after',
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__layer, {{WRAPPER}} .digi-flip-box__overlay' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'flip_effect',
			array(
				'label' => esc_html__( 'Efecto del giro', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'flip',
				'options' => array(
					'flip' => esc_html__( 'Giro', 'pro-elements' ),
					'slide' => esc_html__( 'Deslizar', 'pro-elements' ),
					'push' => esc_html__( 'Empujar', 'pro-elements' ),
					'zoom-in' => esc_html__( 'Acercar', 'pro-elements' ),
					'zoom-out' => esc_html__( 'Alejar', 'pro-elements' ),
					'fade' => esc_html__( 'Fundido', 'pro-elements' ),
				),
				'prefix_class' => 'digi-flip-box--effect-',
			)
		);

		$this->add_control(
			'flip_direction',
			array(
				'label' => esc_html__( 'Dirección del giro', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => 'up',
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-h-align-left',
					),
					'right' => array(
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-h-align-right',
					),
					'up' => array(
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon' => 'eicon-v-align-top',
					),
					'down' => array(
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon' => 'eicon-v-align-bottom',
					),
				),
				'toggle' => false,
				'condition' => array(
					'flip_effect!' => array(
						'fade',
						'zoom-in',
						'zoom-out',
					),
				),
				'prefix_class' => 'digi-flip-box--direction-',
			)
		);

		$this->add_control(
			'flip_3d',
			array(
				'label' => esc_html__( 'Profundidad 3D', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'digi-flip-box--3d',
				'default' => '',
				'prefix_class' => '',
				'condition' => array(
					'flip_effect' => 'flip',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_a',
			array(
				'label' => esc_html__( 'Anverso', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'background_a',
				'types' => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .digi-flip-box__front',
			)
		);

		$this->add_control(
			'background_overlay_a',
			array(
				'label' => esc_html__( 'Superposición del fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__front .digi-flip-box__overlay' => 'background-color: {{VALUE}};',
				),
				'separator' => 'before',
				'condition' => array(
					'background_a_image[id]!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name' => 'background_overlay_a_filters',
				'selector' => '{{WRAPPER}} .digi-flip-box__front .digi-flip-box__overlay',
				'condition' => array(
					'background_overlay_a!' => '',
				),
			)
		);

		$this->add_control(
			'background_overlay_a_blend_mode',
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
					'{{WRAPPER}} .digi-flip-box__front .digi-flip-box__overlay' => 'mix-blend-mode: {{VALUE}}',
				),
				'condition' => array(
					'background_overlay_a!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'padding_a',
			array(
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__front .digi-flip-box__overlay' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'alignment_a',
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
					'{{WRAPPER}} .digi-flip-box__front .digi-flip-box__overlay' => 'text-align: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'vertical_position_a',
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
				'selectors_dictionary' => array(
					'top' => 'flex-start',
					'middle' => 'center',
					'bottom' => 'flex-end',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__front .digi-flip-box__overlay' => 'justify-content: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'border_a',
				'selector' => '{{WRAPPER}} .digi-flip-box__front',
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'shadow_a',
				'selector' => '{{WRAPPER}} .digi-flip-box__front',
			)
		);

		$this->add_control(
			'heading_image_style',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Imagen', 'pro-elements' ),
				'condition' => array(
					'graphic_element' => 'image',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'image_spacing',
			array(
				'label' => esc_html__( 'Separación', 'pro-elements' ),
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
					'{{WRAPPER}} .digi-flip-box__image' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_control(
			'image_width',
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
					'{{WRAPPER}} .digi-flip-box__image img' => 'width: {{SIZE}}{{UNIT}}',
				),
				'condition' => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_control(
			'image_opacity',
			array(
				'label' => esc_html__( 'Opacidad', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'default' => array(
					'size' => 1,
				),
				'range' => array(
					'px' => array(
						'max' => 1,
						'min' => 0.10,
						'step' => 0.01,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__image' => 'opacity: {{SIZE}};',
				),
				'condition' => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'image_border',
				'selector' => '{{WRAPPER}} .digi-flip-box__image img',
				'condition' => array(
					'graphic_element' => 'image',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'image_border_radius',
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
					'{{WRAPPER}} .digi-flip-box__image img' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
				'condition' => array(
					'graphic_element' => 'image',
				),
			)
		);

		$this->add_control(
			'heading_icon_style',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'condition' => array(
					'graphic_element' => 'icon',
				),
				'separator' => 'before',
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
				'label' => esc_html__( 'Separación', 'pro-elements' ),
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
					'{{WRAPPER}} .elementor-view-framed .elementor-icon svg, {{WRAPPER}} .elementor-view-default .elementor-icon svg' => 'fill: {{VALUE}}; border-color: {{VALUE}}',
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
					'{{WRAPPER}} .elementor-icon svg' => 'width: {{SIZE}}{{UNIT}};',
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
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon' => 'padding: {{SIZE}}{{UNIT}};',
				),
				'range' => array(
					'em' => array(
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
			'icon_rotate',
			array(
				'label' => esc_html__( 'Giro del icono', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'deg', 'grad', 'rad', 'turn' ),
				'default' => array(
					'size' => 0,
					'unit' => 'deg',
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-icon i' => 'transform: rotate({{SIZE}}{{UNIT}});',
					'{{WRAPPER}} .elementor-icon svg' => 'transform: rotate({{SIZE}}{{UNIT}});',
				),
				'condition' => array(
					'graphic_element' => 'icon',
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

		$this->add_control(
			'heading_title_style_a',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'separator' => 'before',
				'condition' => array(
					'title_text_a!' => '',
				),
			)
		);

		$this->add_control(
			'title_spacing_a',
			array(
				'label' => esc_html__( 'Separación', 'pro-elements' ),
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
					'{{WRAPPER}} .digi-flip-box__front .digi-flip-box__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'description_text_a!' => '',
					'title_text_a!' => '',
				),
			)
		);

		$this->add_control(
			'title_color_a',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__front .digi-flip-box__title' => 'color: {{VALUE}}',

				),
				'condition' => array(
					'title_text_a!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'title_typography_a',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .digi-flip-box__front .digi-flip-box__title',
				'condition' => array(
					'title_text_a!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name' => 'text_stroke',
				'selector' => '{{WRAPPER}} .digi-flip-box__front .digi-flip-box__title',
			)
		);

		$this->add_control(
			'heading_description_style_a',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
				'separator' => 'before',
				'condition' => array(
					'description_text_a!' => '',
				),
			)
		);

		$this->add_control(
			'description_color_a',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__front .digi-flip-box__description' => 'color: {{VALUE}}',

				),
				'condition' => array(
					'description_text_a!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'description_typography_a',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'selector' => '{{WRAPPER}} .digi-flip-box__front .digi-flip-box__description',
				'condition' => array(
					'description_text_a!' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_b',
			array(
				'label' => esc_html__( 'Reverso', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'background_b',
				'types' => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .digi-flip-box__back',
			)
		);

		$this->add_control(
			'background_overlay_b',
			array(
				'label' => esc_html__( 'Superposición del fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__back .digi-flip-box__overlay' => 'background-color: {{VALUE}};',
				),
				'separator' => 'before',
				'condition' => array(
					'background_b_image[id]!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name' => 'background_overlay_b_filters',
				'selector' => '{{WRAPPER}} .digi-flip-box__back .digi-flip-box__overlay',
				'condition' => array(
					'background_overlay_b!' => '',
				),
			)
		);

		$this->add_control(
			'background_overlay_b_blend_mode',
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
					'{{WRAPPER}} .digi-flip-box__back .digi-flip-box__overlay' => 'mix-blend-mode: {{VALUE}}',
				),
				'condition' => array(
					'background_overlay_b!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'padding_b',
			array(
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__back .digi-flip-box__overlay' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'alignment_b',
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
					'{{WRAPPER}} .digi-flip-box__back .digi-flip-box__overlay' => 'text-align: {{VALUE}}',
					'{{WRAPPER}} .digi-flip-box__button' => 'margin-inline-{{VALUE}}: 0',
				),
			)
		);

		$this->add_control(
			'vertical_position_b',
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
				'selectors_dictionary' => array(
					'top' => 'flex-start',
					'middle' => 'center',
					'bottom' => 'flex-end',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__back .digi-flip-box__overlay' => 'justify-content: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'border_b',
				'selector' => '{{WRAPPER}} .digi-flip-box__back',
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'shadow_b',
				'selector' => '{{WRAPPER}} .digi-flip-box__back',
			)
		);

		$this->add_control(
			'heading_title_style_b',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'separator' => 'before',
				'condition' => array(
					'title_text_b!' => '',
				),
			)
		);

		$this->add_control(
			'title_spacing_b',
			array(
				'label' => esc_html__( 'Separación', 'pro-elements' ),
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
					'{{WRAPPER}} .digi-flip-box__back .digi-flip-box__title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'title_text_b!' => '',
				),
			)
		);

		$this->add_control(
			'title_color_b',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__back .digi-flip-box__title' => 'color: {{VALUE}}',

				),
				'condition' => array(
					'title_text_b!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'title_typography_b',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .digi-flip-box__back .digi-flip-box__title',
				'condition' => array(
					'title_text_b!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name' => 'text_stroke_b',
				'selector' => '{{WRAPPER}} .digi-flip-box__back .digi-flip-box__title',
			)
		);

		$this->add_control(
			'heading_description_style_b',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
				'separator' => 'before',
				'condition' => array(
					'description_text_b!' => '',
				),
			)
		);

		$this->add_control(
			'description_spacing_b',
			array(
				'label' => esc_html__( 'Separación', 'pro-elements' ),
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
					'{{WRAPPER}} .digi-flip-box__back .digi-flip-box__description' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'description_text_b!' => '',
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'description_color_b',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__back .digi-flip-box__description' => 'color: {{VALUE}}',

				),
				'condition' => array(
					'description_text_b!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'description_typography_b',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'selector' => '{{WRAPPER}} .digi-flip-box__back .digi-flip-box__description',
				'condition' => array(
					'description_text_b!' => '',
				),
			)
		);

		$this->add_control(
			'heading_button',
			array(
				'type' => Controls_Manager::HEADING,
				'label' => esc_html__( 'Botón', 'pro-elements' ),
				'separator' => 'before',
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_size',
			array(
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'sm',
				'options' => array(
					'xs' => esc_html__( 'Extra pequeño', 'pro-elements' ),
					'sm' => esc_html__( 'Pequeño', 'pro-elements' ),
					'md' => esc_html__( 'Mediano', 'pro-elements' ),
					'lg' => esc_html__( 'Grande', 'pro-elements' ),
					'xl' => esc_html__( 'Extra grande', 'pro-elements' ),
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'button_typography',
				'selector' => '{{WRAPPER}} .digi-flip-box__button',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->start_controls_tabs( 'button_tabs' );

		$this->start_controls_tab( 'normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__button' => 'color: {{VALUE}};',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'button_background',
				'types' => array( 'classic', 'gradient' ),
				'exclude' => array( 'image' ),
				'selector' => '{{WRAPPER}} .digi-flip-box__button',
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
					),
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_border_color',
			array(
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__button' => 'border-color: {{VALUE}};',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'hover',
			array(
				'label' => esc_html__( 'Al pasar el ratón', 'pro-elements' ),
				'condition' => array(
					'button_text!' => '',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_hover_text_color',
			array(
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__button:hover' => 'color: {{VALUE}};',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'button_hover_background',
				'types' => array( 'classic', 'gradient' ),
				'exclude' => array( 'image' ),
				'selector' => '{{WRAPPER}} .digi-flip-box__button:hover',
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
					),
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_hover_border_color',
			array(
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__button:hover' => 'border-color: {{VALUE}};',
				),
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->add_control(
			'button_hover_transition_duration',
			array(
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default' => array(
					'unit' => 'ms',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-flip-box__button' => 'transition-duration: {{SIZE}}{{UNIT}};',
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
					'{{WRAPPER}} .digi-flip-box__button' => 'border-width: {{SIZE}}{{UNIT}};',
				),
				'separator' => 'before',
				'condition' => array(
					'button_text!' => '',
				),
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
					'{{WRAPPER}} .digi-flip-box__button' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
				'separator' => 'after',
				'condition' => array(
					'button_text!' => '',
				),
			)
		);

		$this->end_controls_section();

		}

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$title_tag = Cards::tag( $settings['title_tag'] ?? 'h3', 'h3' );
		$desc_tag  = Cards::tag( $settings['description_tag'] ?? 'div', 'div' );
		$graphic   = (string) ( $settings['graphic_element'] ?? 'icon' );
		$link      = Cards::link( $settings['link'] ?? array() );
		$box       = $link && 'box' === ( $settings['link_click'] ?? 'button' );
		$button    = (string) ( $settings['button_text'] ?? '' );

		$front = '';
		if ( 'image' === $graphic && ( '' !== (string) ( $settings['image']['url'] ?? '' ) || ! empty( $settings['image']['id'] ) ) ) {
			$img = Cards::image( (array) $settings['image'], $settings, 'image' );
			if ( '' !== $img ) {
				$front .= '<div class="digi-flip-box__image">' . $img . '</div>';
			}
		} elseif ( 'icon' === $graphic ) {
			$icon = Cards::icon( $settings, 'selected_icon' );
			if ( '' !== $icon ) {
				$view  = in_array( $settings['icon_view'] ?? 'default', array( 'default', 'stacked', 'framed' ), true ) ? (string) ( $settings['icon_view'] ?? 'default' ) : 'default';
				$class = 'elementor-icon-wrapper digi-flip-box__icon elementor-view-' . $view;
				if ( 'default' !== $view ) {
					$class .= ' elementor-shape-' . ( 'square' === ( $settings['icon_shape'] ?? 'circle' ) ? 'square' : 'circle' );
				}
				$front .= '<div class="' . $class . '"><div class="elementor-icon">' . $icon . '</div></div>';
			}
		}
		$front .= self::text( $title_tag, 'digi-flip-box__title', $settings['title_text_a'] ?? '' );
		$front .= self::text( $desc_tag, 'digi-flip-box__description', $settings['description_text_a'] ?? '' );

		$back  = self::text( $title_tag, 'digi-flip-box__title', $settings['title_text_b'] ?? '' );
		$back .= self::text( $desc_tag, 'digi-flip-box__description', $settings['description_text_b'] ?? '' );
		if ( '' !== $button ) {
			$attrs = array( 'class' => 'digi-flip-box__button elementor-button elementor-size-' . Cards::button_size( $settings['button_size'] ?? 'sm', 'sm' ) );
			$btag  = $link && ! $box ? 'a' : 'span';
			if ( 'a' === $btag ) {
				$attrs += $link;
			}
			$back .= '<' . $btag . ' ' . Cards::attributes( $attrs ) . '>' . wp_kses_post( $button ) . '</' . $btag . '>';
		}

		// Sin enlace en el reverso, la tarjeta se puede enfocar para mostrarlo con el teclado.
		$effect    = (string) ( $settings['flip_effect'] ?? 'flip' );
		$direction = (string) ( $settings['flip_direction'] ?? 'up' );
		$root      = array( 'class' => 'digi-flip-box', 'style' => in_array( $effect, array( 'flip', 'slide', 'push' ), true ) ? ( self::DIRECTIONS[ $direction ] ?? null ) : null );
		if ( ! $link ) {
			$name = Cards::plain( $settings['title_text_a'] ?? '' );
			$root += array(
				'tabindex'   => '0',
				'role'       => 'group',
				'aria-label' => '' !== $name ? $name : esc_html__( 'Tarjeta giratoria', 'pro-elements' ),
			);
		}
		$back_attrs = array( 'class' => 'digi-flip-box__layer digi-flip-box__back' ) + ( $box ? $link : array() );
		$back_tag   = $box ? 'a' : 'div';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos con Cards::attributes(), textos con wp_kses_post() e imagen de Image_Markup.
		echo '<div ' . Cards::attributes( $root ) . '>';
		echo '<div class="digi-flip-box__layer digi-flip-box__front"><div class="digi-flip-box__overlay"><div class="digi-flip-box__inner">' . $front . '</div></div></div>';
		echo '<' . $back_tag . ' ' . Cards::attributes( $back_attrs ) . '><div class="digi-flip-box__overlay"><div class="digi-flip-box__inner">' . $back . '</div></div></' . $back_tag . '>';
		echo '</div>';
		// phpcs:enable
	}

	/** Título o descripción con su etiqueta permitida; '' sin texto. */
	private static function text( string $tag, string $class, $text ): string {
		$text = (string) $text;
		return '' === $text ? '' : '<' . $tag . ' class="' . $class . '">' . wp_kses_post( $text ) . '</' . $tag . '>';
	}

	/** Markdown como el de PRO: título y descripción de cada cara, y el botón. */
	public function render_markdown(): string {
		$settings = $this->get_settings_for_display();
		$lines    = array();
		foreach ( array( 'title_text_a' => '### ', 'description_text_a' => '', 'title_text_b' => '#### ', 'description_text_b' => '' ) as $key => $prefix ) {
			$text = Cards::plain( $settings[ $key ] ?? '' );
			if ( '' !== $text ) {
				$lines[] = $prefix . $text;
			}
		}
		$button = Cards::plain( $settings['button_text'] ?? '' );
		if ( '' !== $button ) {
			$lines[] = Cards::markdown_button( $button, (string) ( $settings['link']['url'] ?? '' ) );
		}
		return implode( "\n\n", $lines );
	}
}
