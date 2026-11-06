<?php
namespace Digitalisimo\Elements\Carousel\Widgets;

use Digitalisimo\Elements\Carousel\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Diapositivas (`digitalisimo-slides`): sustituye al widget Slides de PRO (`modules/slides/widgets/slides.php`, mapa
 * `modules/digitalisimo-convert/maps/slides.php`). Tiene un control por cada control de aquél, con el mismo nombre, los
 * mismos valores por defecto y las mismas condiciones (también los del repetidor y su estilo por diapositiva con
 * `{{CURRENT_ITEM}}`), y pinta sobre el motor propio `digi-crs` (`Engine`): sin Swiper, sin jQuery y sin los paquetes
 * de PRO. Controles y estructura derivados de PRO Elements (GPL); el marcado, las clases y la hoja son propios.
 *
 * - Fondo por diapositiva: color, imagen (una etiqueta `img` de `Image_Markup`, con las decisiones de carga de WordPress, así la
 *   imagen de la primera diapositiva es descubrible y no retrasa el LCP como un fondo CSS), su ajuste (`object-fit`),
 *   Ken Burns con su dirección y superposición con color y mezcla.
 * - Ken Burns va sólo con CSS sobre la diapositiva actual que marca el motor (`digi-crs__current`). La animación del
 *   contenido la da `assets/js/slides.js` (748 B, sin dependencias): con cada `digi-crs-change` oculta el contenido
 *   (`digi-slides--out`) y, al terminar la transición (`--digi-crs-speed`), lo deja entrar con la animación elegida, que
 *   es una transición CSS. Como en PRO, la diapositiva inicial no se anima (y no retrasa el LCP) y el contenido de las
 *   demás queda pintado fuera de la vista; con movimiento reducido no se oculta nada.
 * - Los botones usan las clases del núcleo `elementor-button` y `elementor-size-*` (no son de PRO): así reciben los
 *   estilos de botón del kit del sitio, como en PRO.
 * - El rasgo «Plantilla en lugar del texto» (`Slides_Templates`, campo `digitalisimo_template_id`) con las mismas reglas
 *   que en el widget de PRO: una plantilla publicada ocupa el lugar de la descripción.
 * - Sin plantilla JS del editor: el editor pinta lo mismo que el servidor (y así muestra las plantillas de cada
 *   diapositiva), y el motor se vuelve a iniciar con `element_ready`.
 */
class Slides extends \Elementor\Widget_Base {
	/** Animaciones del contenido de PRO (`Control_Slides_Animation`). */
	const ANIMATIONS = array( 'fadeInDown', 'fadeInUp', 'fadeInRight', 'fadeInLeft', 'zoomIn' );

	const TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );

	public function get_name() { return 'digitalisimo-slides'; }
	public function get_title() { return 'Diapositivas'; }
	public function get_icon() { return 'eicon-slides'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'diapositivas', 'slides', 'carrusel', 'carousel', 'slider', 'imagen', 'título' ); }
	public function get_style_depends() { return array( Engine::HANDLE, 'digitalisimo-slides' ); }
	public function get_script_depends() { return array( Engine::HANDLE, 'digitalisimo-slides' ); }

	/** Tamaños del botón (los del núcleo: `elementor-size-*`). */
	public static function get_button_sizes() {
		return array( 'xs' => 'Extra pequeño', 'sm' => 'Pequeño', 'md' => 'Mediano', 'lg' => 'Grande', 'xl' => 'Extra grande' );
	}

	protected function register_controls() {
		$c      = '\\Elementor\\Controls_Manager';
		$tags   = array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div', 'span' => 'span', 'p' => 'p' );
		$custom = array( 'terms' => array( array( 'name' => 'custom_style', 'value' => 'yes' ) ) );
		$image  = array( 'terms' => array( array( 'name' => 'background_image[url]', 'operator' => '!=', 'value' => '' ) ) );
		$h_pos  = array(
			'left'   => array( 'title' => 'Izquierda', 'icon' => 'eicon-h-align-left' ),
			'center' => array( 'title' => 'Centro', 'icon' => 'eicon-h-align-center' ),
			'right'  => array( 'title' => 'Derecha', 'icon' => 'eicon-h-align-right' ),
		);
		$v_pos  = array(
			'top'    => array( 'title' => 'Arriba', 'icon' => 'eicon-v-align-top' ),
			'middle' => array( 'title' => 'En medio', 'icon' => 'eicon-v-align-middle' ),
			'bottom' => array( 'title' => 'Abajo', 'icon' => 'eicon-v-align-bottom' ),
		);
		$align  = array(
			'start'  => array( 'title' => 'Inicio', 'icon' => 'eicon-text-align-left' ),
			'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ),
			'end'    => array( 'title' => 'Fin', 'icon' => 'eicon-text-align-right' ),
		);
		$rtl    = function_exists( 'is_rtl' ) && is_rtl();
		$a_dict = array( 'left' => $rtl ? 'end' : 'start', 'right' => $rtl ? 'start' : 'end' );
		$spacing = array( 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem', 'custom' ), 'range' => array( 'px' => array( 'max' => 100 ), 'em' => array( 'max' => 10 ), 'rem' => array( 'max' => 10 ) ) );
		$nav_both = array( 'navigation' => array( 'arrows', 'both' ) );
		$nav_dots = array( 'navigation' => array( 'dots', 'both' ) );

		$this->start_controls_section( 'section_slides', array( 'label' => 'Diapositivas' ) );
		$this->add_control( 'slides_name', array( 'label' => 'Nombre del carrusel', 'type' => $c::TEXT, 'default' => 'Diapositivas' ) );

		$repeater = new \Elementor\Repeater();
		$repeater->start_controls_tabs( 'slides_repeater' );
		$repeater->start_controls_tab( 'background', array( 'label' => 'Fondo' ) );
		$repeater->add_control( 'background_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'default' => '#bbbbbb', 'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__bg' => 'background-color: {{VALUE}}' ) ) );
		// La imagen se pinta como etiqueta `img` (no como fondo CSS): sin `selectors`, para no descargarla dos veces.
		$repeater->add_control( 'background_image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'default' => array( 'url' => '' ) ) );
		$repeater->add_control(
			'background_size',
			array(
				'label'                => 'Tamaño',
				'type'                 => $c::SELECT,
				'default'              => 'cover',
				'options'              => array( 'cover' => 'Cubrir', 'contain' => 'Contener', 'auto' => 'Automático' ),
				'selectors_dictionary' => array( 'auto' => 'none' ),
				'selectors'            => array( '{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__img' => 'object-fit: {{VALUE}}' ),
				'conditions'           => $image,
			)
		);
		$repeater->add_control( 'background_ken_burns', array( 'label' => 'Efecto Ken Burns', 'type' => $c::SWITCHER, 'default' => '', 'conditions' => $image ) );
		$repeater->add_control( 'zoom_direction', array( 'label' => 'Dirección del zoom', 'type' => $c::SELECT, 'default' => 'in', 'options' => array( 'in' => 'Acercar', 'out' => 'Alejar' ), 'conditions' => array( 'terms' => array( array( 'name' => 'background_ken_burns', 'operator' => '!=', 'value' => '' ) ) ) ) );
		$repeater->add_control( 'background_overlay', array( 'label' => 'Superposición del fondo', 'type' => $c::SWITCHER, 'default' => '', 'conditions' => $image ) );
		$overlay = array( 'terms' => array( array( 'name' => 'background_overlay', 'value' => 'yes' ) ) );
		$repeater->add_control( 'background_overlay_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'default' => 'rgba(0,0,0,0.5)', 'conditions' => $overlay, 'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__overlay' => 'background-color: {{VALUE}}' ) ) );
		$repeater->add_control(
			'background_overlay_blend_mode',
			array(
				'label'      => 'Modo de mezcla',
				'type'       => $c::SELECT,
				'options'    => array( '' => 'Normal', 'multiply' => 'Multiplicar', 'screen' => 'Trama', 'overlay' => 'Superponer', 'darken' => 'Oscurecer', 'lighten' => 'Aclarar', 'color-dodge' => 'Sobreexponer color', 'color-burn' => 'Subexponer color', 'hue' => 'Tono', 'saturation' => 'Saturación', 'color' => 'Color', 'exclusion' => 'Exclusión', 'luminosity' => 'Luminosidad' ),
				'conditions' => $overlay,
				'selectors'  => array( '{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__overlay' => 'mix-blend-mode: {{VALUE}}' ),
			)
		);
		$repeater->end_controls_tab();

		$repeater->start_controls_tab( 'content', array( 'label' => 'Contenido' ) );
		$repeater->add_control( 'heading', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => 'Título de la diapositiva', 'label_block' => true, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'description', array( 'label' => 'Descripción', 'type' => $c::TEXTAREA, 'default' => 'Escribe aquí el texto de la diapositiva: una idea breve que acompañe al título y lleve al botón.', 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'button_text', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'default' => 'Haz clic aquí', 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control(
			'link_click',
			array(
				'label'      => 'Aplicar el enlace en',
				'type'       => $c::SELECT,
				'options'    => array( 'slide' => 'Toda la diapositiva', 'button' => 'Sólo el botón' ),
				'default'    => 'slide',
				'conditions' => array( 'terms' => array( array( 'name' => 'link[url]', 'operator' => '!=', 'value' => '' ) ) ),
			)
		);
		$repeater->end_controls_tab();

		$repeater->start_controls_tab( 'style', array( 'label' => 'Estilo' ) );
		$repeater->add_control( 'custom_style', array( 'label' => 'Personalizado', 'type' => $c::SWITCHER, 'description' => 'Estilo propio que sólo afecta a esta diapositiva.' ) );
		$repeater->add_control(
			'horizontal_position',
			array(
				'label'                => 'Posición horizontal',
				'type'                 => $c::CHOOSE,
				'options'              => $h_pos,
				'selectors'            => array( '{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__contents' => '{{VALUE}}' ),
				'selectors_dictionary' => array( 'left' => 'margin-right: auto', 'center' => 'margin: 0 auto', 'right' => 'margin-left: auto' ),
				'conditions'           => $custom,
			)
		);
		$repeater->add_control(
			'vertical_position',
			array(
				'label'                => 'Posición vertical',
				'type'                 => $c::CHOOSE,
				'options'              => $v_pos,
				'selectors'            => array( '{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__inner' => 'align-items: {{VALUE}}' ),
				'selectors_dictionary' => array( 'top' => 'flex-start', 'middle' => 'center', 'bottom' => 'flex-end' ),
				'conditions'           => $custom,
			)
		);
		$repeater->add_control(
			'text_align',
			array(
				'label'                => 'Alineación del texto',
				'type'                 => $c::CHOOSE,
				'options'              => $align,
				'classes'              => 'elementor-control-start-end',
				'selectors_dictionary' => $a_dict,
				'selectors'            => array( '{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__inner' => 'text-align: {{VALUE}}' ),
				'conditions'           => $custom,
			)
		);
		$repeater->add_control(
			'content_color',
			array(
				'label'      => 'Color del contenido',
				'type'       => $c::COLOR,
				'selectors'  => array(
					'{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__inner .digi-slides__heading'     => 'color: {{VALUE}}',
					'{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__inner .digi-slides__description' => 'color: {{VALUE}}',
					'{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__inner .digi-slides__button'      => 'color: {{VALUE}}; border-color: {{VALUE}}',
				),
				'conditions' => $custom,
			)
		);
		$repeater->add_group_control( \Elementor\Group_Control_Text_Shadow::get_type(), array( 'name' => 'repeater_text_shadow', 'selector' => '{{WRAPPER}} {{CURRENT_ITEM}} .digi-slides__contents', 'conditions' => $custom ) );
		$repeater->end_controls_tab();
		$repeater->end_controls_tabs();

		$row = static function ( $n, $color ) {
			return array( 'heading' => 'Título de la diapositiva ' . $n, 'description' => 'Escribe aquí el texto de la diapositiva: una idea breve que acompañe al título.', 'button_text' => 'Haz clic aquí', 'background_color' => $color, 'background_image' => array( 'url' => '' ) );
		};
		$this->add_control(
			'slides',
			array(
				'label'       => 'Diapositivas',
				'type'        => $c::REPEATER,
				'show_label'  => true,
				'fields'      => $repeater->get_controls(),
				'default'     => array( $row( 1, '#833ca3' ), $row( 2, '#4054b2' ), $row( 3, '#1abc9c' ) ),
				'title_field' => '{{{ heading }}}',
			)
		);
		$this->add_responsive_control(
			'slides_height',
			array(
				'label'      => 'Altura',
				'type'       => $c::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array( 'px' => array( 'min' => 100, 'max' => 1000 ), 'em' => array( 'min' => 10, 'max' => 100 ), 'rem' => array( 'min' => 10, 'max' => 100 ), 'vh' => array( 'min' => 10, 'max' => 100 ) ),
				'default'    => array( 'size' => 400 ),
				'selectors'  => array( '{{WRAPPER}} .digi-slides__slide' => 'height: {{SIZE}}{{UNIT}};' ),
				'separator'  => 'before',
			)
		);
		$this->add_control( 'slides_title_tag', array( 'label' => 'Etiqueta HTML del título', 'type' => $c::SELECT, 'options' => $tags, 'default' => 'div' ) );
		$this->add_control( 'slides_description_tag', array( 'label' => 'Etiqueta HTML de la descripción', 'type' => $c::SELECT, 'options' => $tags, 'default' => 'div' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_slider_options', array( 'label' => 'Opciones del carrusel', 'type' => $c::SECTION ) );
		$this->add_control( 'navigation', array( 'label' => 'Navegación', 'type' => $c::SELECT, 'default' => 'both', 'options' => array( 'both' => 'Flechas y puntos', 'arrows' => 'Flechas', 'dots' => 'Puntos', 'none' => 'Ninguna' ), 'frontend_available' => true ) );
		$this->add_control( 'autoplay', array( 'label' => 'Reproducción automática', 'type' => $c::SWITCHER, 'default' => 'yes', 'frontend_available' => true ) );
		$this->add_control( 'pause_on_hover', array( 'label' => 'Pausar al pasar el cursor', 'type' => $c::SWITCHER, 'default' => 'yes', 'frontend_available' => true, 'condition' => array( 'autoplay!' => '' ) ) );
		$this->add_control( 'pause_on_interaction', array( 'label' => 'Pausar al interactuar', 'type' => $c::SWITCHER, 'default' => 'yes', 'frontend_available' => true, 'condition' => array( 'autoplay!' => '' ) ) );
		$this->add_control( 'autoplay_speed', array( 'label' => 'Velocidad de reproducción automática', 'type' => $c::NUMBER, 'default' => 5000, 'condition' => array( 'autoplay' => 'yes' ), 'frontend_available' => true ) );
		$this->add_control( 'infinite', array( 'label' => 'Bucle infinito', 'type' => $c::SWITCHER, 'default' => 'yes', 'frontend_available' => true ) );
		$this->add_control( 'transition', array( 'label' => 'Transición', 'type' => $c::SELECT, 'default' => 'slide', 'options' => array( 'slide' => 'Deslizar', 'fade' => 'Fundido' ), 'frontend_available' => true ) );
		$this->add_control( 'transition_speed', array( 'label' => 'Velocidad de la transición (ms)', 'type' => $c::NUMBER, 'default' => 500, 'frontend_available' => true ) );
		$this->add_control(
			'content_animation',
			array(
				'label'   => 'Animación del contenido',
				'type'    => $c::SELECT,
				'default' => 'fadeInUp',
				'options' => array( '' => 'Ninguna', 'fadeInDown' => 'Hacia abajo', 'fadeInUp' => 'Hacia arriba', 'fadeInRight' => 'Hacia la derecha', 'fadeInLeft' => 'Hacia la izquierda', 'zoomIn' => 'Zoom' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_slides', array( 'label' => 'Diapositivas', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control(
			'content_max_width',
			array(
				'label'          => 'Ancho del contenido',
				'type'           => $c::SLIDER,
				'size_units'     => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'          => array( 'px' => array( 'max' => 1000 ), 'em' => array( 'max' => 100 ), 'rem' => array( 'max' => 100 ) ),
				'default'        => array( 'size' => 66, 'unit' => '%' ),
				'tablet_default' => array( 'unit' => '%' ),
				'mobile_default' => array( 'unit' => '%' ),
				'selectors'      => array( '{{WRAPPER}} .digi-slides__contents' => 'max-width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control( 'slides_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ), 'selectors' => array( '{{WRAPPER}} .digi-slides__inner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'slides_horizontal_position', array( 'label' => 'Posición horizontal', 'type' => $c::CHOOSE, 'default' => 'center', 'options' => $h_pos, 'prefix_class' => 'digi-slides--h-' ) );
		$this->add_control( 'slides_vertical_position', array( 'label' => 'Posición vertical', 'type' => $c::CHOOSE, 'default' => 'middle', 'options' => $v_pos, 'prefix_class' => 'digi-slides--v-' ) );
		$this->add_control( 'slides_text_align', array( 'label' => 'Alineación del texto', 'type' => $c::CHOOSE, 'options' => $align, 'default' => 'center', 'classes' => 'elementor-control-start-end', 'selectors_dictionary' => $a_dict, 'selectors' => array( '{{WRAPPER}} .digi-slides__inner' => 'text-align: {{VALUE}}' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Text_Shadow::get_type(), array( 'name' => 'text_shadow', 'selector' => '{{WRAPPER}} .digi-slides__contents' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_title', array( 'label' => 'Título', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'heading_spacing', array( 'label' => 'Separación', 'selectors' => array( '{{WRAPPER}} .digi-slides__inner .digi-slides__heading:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}}' ) ) + $spacing );
		$this->add_control( 'heading_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-slides__heading' => 'color: {{VALUE}}' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'heading_typography', 'global' => array( 'default' => 'globals/typography?id=primary' ), 'selector' => '{{WRAPPER}} .digi-slides__heading' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_description', array( 'label' => 'Descripción', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'description_spacing', array( 'label' => 'Separación', 'selectors' => array( '{{WRAPPER}} .digi-slides__inner .digi-slides__description:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}}' ) ) + $spacing );
		$this->add_control( 'description_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-slides__description' => 'color: {{VALUE}}' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'description_typography', 'global' => array( 'default' => 'globals/typography?id=secondary' ), 'selector' => '{{WRAPPER}} .digi-slides__description' ) );
		$this->end_controls_section();

		$button = '{{WRAPPER}} .digi-slides__button';
		$this->start_controls_section( 'section_style_button', array( 'label' => 'Botón', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'button_size', array( 'label' => 'Tamaño', 'type' => $c::SELECT, 'default' => 'sm', 'options' => self::get_button_sizes() ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'button_typography', 'selector' => $button, 'global' => array( 'default' => 'globals/typography?id=accent' ) ) );
		$this->add_control( 'button_border_width', array( 'label' => 'Ancho del borde', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ), 'range' => array( 'px' => array( 'max' => 20 ), 'em' => array( 'max' => 2 ), 'rem' => array( 'max' => 2 ) ), 'selectors' => array( $button => 'border-width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'button_border_radius', array( 'label' => 'Radio del borde', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ), 'range' => array( 'px' => array( 'max' => 100 ), 'em' => array( 'max' => 10 ), 'rem' => array( 'max' => 10 ) ), 'selectors' => array( $button => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->start_controls_tabs( 'button_tabs' );
		$this->start_controls_tab( 'normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'button_text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( $button => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Background::get_type(), array( 'name' => 'button_background', 'types' => array( 'classic', 'gradient' ), 'exclude' => array( 'image' ), 'selector' => $button, 'fields_options' => array( 'background' => array( 'default' => 'classic' ) ) ) );
		$this->add_control( 'button_border_color', array( 'label' => 'Color del borde', 'type' => $c::COLOR, 'selectors' => array( $button => 'border-color: {{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'hover', array( 'label' => 'Al pasar el cursor' ) );
		$this->add_control( 'button_hover_text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( $button . ':hover' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Background::get_type(), array( 'name' => 'button_hover_background', 'types' => array( 'classic', 'gradient' ), 'exclude' => array( 'image' ), 'selector' => $button . ':hover', 'fields_options' => array( 'background' => array( 'default' => 'classic' ) ) ) );
		$this->add_control( 'button_hover_border_color', array( 'label' => 'Color del borde', 'type' => $c::COLOR, 'selectors' => array( $button . ':hover' => 'border-color: {{VALUE}};' ) ) );
		$this->add_control( 'button_hover_transition_duration', array( 'label' => 'Duración de la transición', 'type' => $c::SLIDER, 'size_units' => array( 's', 'ms', 'custom' ), 'default' => array( 'unit' => 'ms' ), 'selectors' => array( $button => 'transition-duration: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_navigation', array( 'label' => 'Navegación', 'tab' => $c::TAB_STYLE, 'condition' => array( 'navigation' => array( 'arrows', 'dots', 'both' ) ) ) );
		$this->add_control( 'heading_style_arrows', array( 'label' => 'Flechas', 'type' => $c::HEADING, 'separator' => 'before', 'condition' => $nav_both ) );
		// Las posiciones van a la raíz del carrusel (`Engine::open()`: `digi-crs--arrows-out`, `digi-crs--pag-out`).
		$this->add_control( 'arrows_position', array( 'label' => 'Posición', 'type' => $c::SELECT, 'default' => 'inside', 'options' => array( 'inside' => 'Dentro', 'outside' => 'Fuera' ), 'condition' => $nav_both ) );
		$this->add_responsive_control( 'arrows_size', array( 'label' => 'Tamaño', 'selectors' => array( '{{WRAPPER}}' => '--digi-crs-arrow-size: {{SIZE}}{{UNIT}}' ), 'condition' => $nav_both ) + $spacing );
		$this->add_control( 'arrows_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}}' => '--digi-crs-arrow-color: {{VALUE}}' ), 'condition' => $nav_both ) );
		$this->add_control( 'heading_style_dots', array( 'label' => 'Paginación', 'type' => $c::HEADING, 'separator' => 'before', 'condition' => $nav_dots ) );
		$this->add_control( 'dots_position', array( 'label' => 'Posición', 'type' => $c::SELECT, 'default' => 'inside', 'options' => array( 'outside' => 'Fuera', 'inside' => 'Dentro' ), 'condition' => $nav_dots ) );
		$this->add_responsive_control( 'dots_gap', array( 'label' => 'Espacio entre puntos', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem', 'custom' ), 'range' => array( 'px' => array( 'max' => 50 ) ), 'selectors' => array( '{{WRAPPER}}' => '--digi-crs-dot-gap: {{SIZE}}{{UNIT}}' ), 'condition' => $nav_dots ) );
		$this->add_responsive_control( 'dots_size', array( 'label' => 'Tamaño', 'selectors' => array( '{{WRAPPER}}' => '--digi-crs-dot-size: {{SIZE}}{{UNIT}}' ), 'condition' => $nav_dots ) + $spacing );
		// Como en PRO, el color de los puntos inactivos anula su opacidad de .2.
		$this->add_control( 'dots_color_inactive', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}}' => '--digi-crs-dot-color: {{VALUE}}; --digi-crs-dot-opacity: 1' ), 'condition' => $nav_dots ) );
		$this->add_control( 'dots_color', array( 'label' => 'Color activo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}}' => '--digi-crs-dot-active: {{VALUE}}' ), 'condition' => $nav_dots ) );
		$this->end_controls_section();
	}

	protected function render() {
		echo $this->markup( (array) $this->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado al construirlo.
	}

	/** Marcado del widget (lo mismo en el front y en el editor). */
	public function markup( array $s ): string {
		$slides = array_values( array_filter( is_array( $s['slides'] ?? null ) ? $s['slides'] : array(), 'is_array' ) );
		$count  = count( $slides );
		if ( ! $count ) {
			return '';
		}
		$navigation = (string) ( $s['navigation'] ?? 'both' );
		$animation  = (string) ( $s['content_animation'] ?? '' );
		$attributes = in_array( $animation, self::ANIMATIONS, true ) ? array( 'data-digi-animation' => $animation ) : array();
		$html       = Engine::open(
			array(
				'label'       => (string) ( $s['slides_name'] ?? '' ),
				'count'       => $count,
				'autoplay'    => 'yes' === ( $s['autoplay'] ?? '' ) ? max( 0, (int) ( $s['autoplay_speed'] ?? 5000 ) ) : 0,
				'loop'        => 'yes' === ( $s['infinite'] ?? '' ),
				'hover'       => 'yes' === ( $s['pause_on_hover'] ?? '' ),
				'interaction' => 'yes' === ( $s['pause_on_interaction'] ?? '' ),
				'effect'      => 'fade' === ( $s['transition'] ?? '' ) ? 'fade' : 'slide',
				'speed'       => max( 0, (int) ( $s['transition_speed'] ?? 500 ) ),
				'pagination'  => $count > 1 && in_array( $navigation, array( 'dots', 'both' ), true ) ? 'bullets' : '',
				'pagination_position' => 'outside' === ( $s['dots_position'] ?? 'inside' ) ? 'outside' : 'inside',
				'arrows'      => $count > 1 && in_array( $navigation, array( 'arrows', 'both' ), true ),
				'arrows_position'     => 'outside' === ( $s['arrows_position'] ?? 'inside' ) ? 'outside' : 'inside',
				'dir'         => function_exists( 'is_rtl' ) && is_rtl() ? 'rtl' : '',
				'classes'     => array( 'digi-slides' ),
				'attributes'  => $attributes,
				'grab'        => true, // `grabCursor` de PRO (sin `slideToClickedSlide`).
			)
		);
		$title_tag = self::tag( $s['slides_title_tag'] ?? 'div' );
		$desc_tag  = self::tag( $s['slides_description_tag'] ?? 'div' );
		$button    = 'elementor-button digi-slides__button' . ( ! empty( $s['button_size'] ) ? ' elementor-size-' . sanitize_html_class( (string) $s['button_size'] ) : '' );
		foreach ( $slides as $index => $slide ) {
			$html .= $this->slide( $slide, $index, $count, $title_tag, $desc_tag, $button );
		}
		return $html . Engine::close();
	}

	private function slide( array $slide, int $index, int $count, string $title_tag, string $desc_tag, string $button ): string {
		$link       = is_array( $slide['link'] ?? null ) ? $slide['link'] : array();
		$link_attrs = '' !== trim( (string) ( $link['url'] ?? '' ) ) ? self::link_attributes( $link ) : '';
		$on_button  = '' !== $link_attrs && 'button' === ( $slide['link_click'] ?? 'slide' );
		$on_slide   = '' !== $link_attrs && ! $on_button;

		$bg = 'digi-slides__bg';
		if ( ! empty( $slide['background_ken_burns'] ) ) {
			$bg .= ' digi-slides__bg--kb digi-slides__bg--kb-' . ( 'out' === ( $slide['zoom_direction'] ?? 'in' ) ? 'out' : 'in' );
		}
		$image = is_array( $slide['background_image'] ?? null ) ? $slide['background_image'] : array();
		$img   = '' !== (string) ( $image['url'] ?? '' ) || ! empty( $image['id'] ) ? \Digitalisimo\Elements\Image_Markup::img( $image, array( 'size' => 'full', 'class' => 'digi-slides__img', 'columns' => array( 1, 1, 1 ) ) ) : '';

		$inner = '';
		if ( '' !== (string) ( $slide['heading'] ?? '' ) ) {
			$inner .= '<' . $title_tag . ' class="digi-slides__heading">' . wp_kses_post( (string) $slide['heading'] ) . '</' . $title_tag . '>';
		}
		$template = class_exists( '\\Digitalisimo\\Elements\\Slides_Templates' ) ? \Digitalisimo\Elements\Slides_Templates::content( $slide ) : '';
		if ( '' !== $template ) {
			$inner .= '<div class="digi-slides__description digi-slides__template">' . $template . '</div>';
		} elseif ( '' !== (string) ( $slide['description'] ?? '' ) ) {
			$inner .= '<' . $desc_tag . ' class="digi-slides__description">' . wp_kses_post( (string) $slide['description'] ) . '</' . $desc_tag . '>';
		}
		if ( '' !== (string) ( $slide['button_text'] ?? '' ) ) {
			$tag    = $on_button ? 'a' : 'div';
			$inner .= '<' . $tag . ' class="' . esc_attr( $button ) . '"' . ( $on_button ? ' ' . $link_attrs : '' ) . '>' . wp_kses_post( (string) $slide['button_text'] ) . '</' . $tag . '>';
		}
		$wrap = $on_slide ? 'a' : 'div';
		// Como PRO, la clase de `{{CURRENT_ITEM}}` va siempre (también en una fila sin `_id`, que el núcleo pinta igual).
		$id = (string) ( $slide['_id'] ?? '' );

		return '<div ' . Engine::slide_attributes( $index, $count, 'digi-slides__slide elementor-repeater-item-' . sanitize_html_class( $id ) ) . '>'
			. '<div class="' . $bg . '">' . $img . '</div>'
			. ( 'yes' === ( $slide['background_overlay'] ?? '' ) ? '<div class="digi-slides__overlay"></div>' : '' )
			. '<' . $wrap . ' class="digi-slides__inner"' . ( $on_slide ? ' ' . $link_attrs : '' ) . '><div class="digi-slides__contents">' . $inner . '</div></' . $wrap . '>'
			. '</div>';
	}

	/** Etiqueta permitida (la lista de PRO); cualquier otra vuelve a `div`. */
	public static function tag( $tag ): string {
		$tag = strtolower( (string) $tag );
		return in_array( $tag, self::TAGS, true ) ? $tag : 'div';
	}

	/** Atributos de un control URL: destino, pestaña nueva, nofollow y atributos personalizados (como `add_link_attributes()`). */
	public static function link_attributes( array $link ): string {
		$protocols = function_exists( 'wp_allowed_protocols' ) ? array_merge( wp_allowed_protocols(), array( 'skype', 'viber' ) ) : null;
		$href      = esc_url( trim( (string) ( $link['url'] ?? '' ) ), $protocols );
		if ( '' === $href ) {
			return '';
		}
		$attrs = array( 'href' => $href );
		if ( ! empty( $link['is_external'] ) ) {
			$attrs['target'] = '_blank';
		}
		if ( ! empty( $link['nofollow'] ) ) {
			$attrs['rel'] = 'nofollow';
		}
		if ( ! empty( $link['custom_attributes'] ) && class_exists( '\\Elementor\\Utils' ) && method_exists( '\\Elementor\\Utils', 'parse_custom_attributes' ) ) {
			foreach ( (array) \Elementor\Utils::parse_custom_attributes( (string) $link['custom_attributes'] ) as $name => $value ) {
				if ( ! isset( $attrs[ $name ] ) ) {
					$attrs[ $name ] = (string) $value;
				}
			}
		}
		$out = array();
		foreach ( $attrs as $name => $value ) {
			$out[] = $name . '="' . ( 'href' === $name ? $value : esc_attr( $value ) ) . '"';
		}
		return implode( ' ', $out );
	}

	/** Markdown como el de PRO: título, descripción, botón e imagen de cada diapositiva. */
	public function render_markdown(): string {
		$blocks = array();
		foreach ( (array) ( $this->get_settings( 'slides' ) ?? array() ) as $slide ) {
			if ( ! is_array( $slide ) ) {
				continue;
			}
			$parts   = array();
			$heading = trim( wp_strip_all_tags( (string) ( $slide['heading'] ?? '' ) ) );
			$desc    = trim( wp_strip_all_tags( (string) ( $slide['description'] ?? '' ) ) );
			$button  = trim( wp_strip_all_tags( (string) ( $slide['button_text'] ?? '' ) ) );
			$url     = (string) ( $slide['link']['url'] ?? '' );
			$image   = (string) ( $slide['background_image']['url'] ?? '' );
			if ( '' !== $heading ) {
				$parts[] = '### ' . $heading;
			}
			if ( '' !== $desc ) {
				$parts[] = $desc;
			}
			if ( '' !== $button ) {
				$parts[] = '' !== $url ? '[**' . $button . '**](' . esc_url( $url ) . ')' : '**' . $button . '**';
			}
			if ( '' !== $image ) {
				$parts[] = '![](' . $image . ')';
			}
			if ( $parts ) {
				$blocks[] = implode( "\n\n", $parts );
			}
		}
		return $blocks ? '## ' . $this->get_title() . "\n\n" . implode( "\n\n", $blocks ) : '';
	}
}
