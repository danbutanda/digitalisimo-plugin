<?php
namespace Digitalisimo\Elements\Carousel\Widgets;

use Digitalisimo\Elements\Carousel\Base_Widget;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Carrusel de testimonios (`digitalisimo-testimonial-carousel`): sustituye al «Testimonial Carousel» de PRO
 * (`modules/carousel/widgets/testimonial-carousel.php`, mapa `modules/digitalisimo-convert/maps/testimonial-carousel.php`).
 * Repite sus controles con el mismo nombre, valor por defecto y condiciones (y los de `Base_Widget`, con los mismos
 * cambios: sin efecto, sin altura y sin posición de la paginación); el marcado, las clases y la hoja son propios, sobre
 * el motor `digi-crs`, sin Swiper ni JavaScript propio.
 *
 * Presentación: pieles `default` y `bubble` (`digi-testimonial--skin-*` en el envoltorio), disposiciones de la imagen
 * (`digi-testimonial--layout-*`) y alineación por dispositivo con variables (`--digi-t-*`, una regla por dispositivo
 * como las clases con medios de PRO). Como en PRO, con la piel «Burbuja» el nombre y el cargo van siempre junto a la
 * imagen.
 *
 * Pico de la burbuja: en un testimonio nuevo apunta a la imagen en cada dispositivo. En lo convertido de PRO, el mapa
 * fija `pro_bubble` y el pico queda donde lo dejaba PRO (`PRO_BUBBLE`).
 *
 * Diferencias declaradas con PRO: en un testimonio nuevo el pico apunta a la imagen también con «Derecha» y en cada
 * dispositivo (PRO lo colocaba sólo con la alineación de escritorio y, con «Derecha», lo dejaba a la izquierda salvo con
 * «Tamaño» de la imagen; lo convertido lo conserva con `pro_bubble`); la imagen se pinta con `wp_get_attachment_image()`
 * (con `srcset`) y la carga diferida es la nativa del navegador, con el indicador de carga de PRO.
 */
class Testimonial_Carousel extends Base_Widget {

	/**
	 * Variables de cada alineación: alineación del texto, dirección y reparto del pie (imagen en línea o arriba),
	 * alineación del nombre junto a la imagen, y posición del pico de la burbuja. Cada valor fija todas (un dispositivo
	 * nunca hereda las de otro) salvo `--digi-t-cta`, que sólo fija «centro»: como en PRO, el nombre junto a la imagen
	 * queda al inicio también en los dispositivos menores que no vuelven a centrar.
	 */
	const ALIGNMENT = array(
		'left'   => '--digi-t-ta:left;--digi-t-fd:row;--digi-t-jc:start;--digi-t-l:var(--digi-t-al,calc(20px + 50px / 2));--digi-t-r:auto;--digi-t-x:0',
		'center' => '--digi-t-ta:center;--digi-t-fd:row;--digi-t-jc:center;--digi-t-cta:start;--digi-t-l:50%;--digi-t-r:auto;--digi-t-x:-50%',
		'right'  => '--digi-t-ta:right;--digi-t-fd:row-reverse;--digi-t-jc:end;--digi-t-l:auto;--digi-t-r:var(--digi-t-ar,calc(20px + 50px / 2));--digi-t-x:0',
	);

	/**
	 * Pico de la burbuja como lo pintaba PRO (control oculto `pro_bubble`, que fija el mapa en lo convertido): su hoja lo
	 * colocaba sólo con la alineación de escritorio (las clases de los dispositivos no lo movían) y, con «Derecha», la
	 * regla del inicio (`inset-inline-start`) ganaba a la de `right`, así que el pico quedaba a la izquierda (a la derecha
	 * en RTL con «Izquierda») salvo en los dispositivos con «Tamaño» de la imagen, cuyas reglas lo llevaban al lado de la
	 * alineación; en RTL, con «Centro», la regla del inicio (la derecha) ganaba a `left: 50%`. `--digi-t-ia` (`auto`) la
	 * escribe «Tamaño»; `--digi-t-al`/`--digi-t-ar`, también. Cada valor fija las
	 * variables del pico en el contenido (gana a las de cada dispositivo, que van en el envoltorio) y deja en
	 * `--digi-t-rl`/`--digi-t-rr` las de RTL, que toma la segunda regla del control. `start` es la alineación vacía.
	 */
	const PRO_BUBBLE = array(
		'start'  => '--digi-t-l:var(--digi-t-al,calc(20px + 50px / 2));--digi-t-r:auto;--digi-t-x:0;--digi-t-rl:auto;--digi-t-rr:var(--digi-t-ar,calc(20px + 50px / 2))',
		'left'   => '--digi-t-l:var(--digi-t-al,calc(20px + 50px / 2));--digi-t-r:auto;--digi-t-x:0;--digi-t-rl:var(--digi-t-al,auto);--digi-t-rr:var(--digi-t-ia,calc(20px + 50px / 2))',
		'center' => '--digi-t-l:50%;--digi-t-r:auto;--digi-t-x:-50%;--digi-t-rl:auto;--digi-t-rr:calc(20px + 50px / 2)',
		'right'  => '--digi-t-l:var(--digi-t-ia,calc(20px + 50px / 2));--digi-t-r:var(--digi-t-ar,auto);--digi-t-x:0;--digi-t-rl:auto;--digi-t-rr:var(--digi-t-ar,calc(20px + 50px / 2))',
	);

	const LAYOUTS = array( 'image_inline', 'image_stacked', 'image_above', 'image_left', 'image_right' );

	public function get_name() {
		return 'digitalisimo-testimonial-carousel';
	}

	/** Como PRO (su script de testimonios, que usan también las reseñas): una visible en cada dispositivo con «Predeterminado». */
	protected function slides_per_view_defaults(): array {
		return array( 'desktop' => '1' );
	}

	public function get_title() {
		return esc_html__( 'Carrusel de testimonios', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-testimonial-carousel';
	}

	public function get_keywords() {
		return array( 'testimonios', 'testimonial', 'carrusel', 'carousel', 'opiniones', 'imagen' );
	}

	protected function register_controls() {
		parent::register_controls();

		$this->start_injection( array( 'of' => 'slides' ) );

		$this->add_control(
			'skin',
			array(
				'label'        => esc_html__( 'Piel', 'pro-elements' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'default',
				'options'      => array(
					'default' => esc_html__( 'Predeterminada', 'pro-elements' ),
					'bubble'  => esc_html__( 'Burbuja', 'pro-elements' ),
				),
				'prefix_class' => 'digi-testimonial--skin-',
				'render_type'  => 'template',
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'        => esc_html__( 'Disposición', 'pro-elements' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'image_inline',
				'options'      => array(
					'image_inline'  => esc_html__( 'Imagen en línea', 'pro-elements' ),
					'image_stacked' => esc_html__( 'Imagen apilada', 'pro-elements' ),
					'image_above'   => esc_html__( 'Imagen arriba', 'pro-elements' ),
					'image_left'    => esc_html__( 'Imagen a la izquierda', 'pro-elements' ),
					'image_right'   => esc_html__( 'Imagen a la derecha', 'pro-elements' ),
				),
				'prefix_class' => 'digi-testimonial--layout-',
				'render_type'  => 'template',
			)
		);

		// Por variables (una regla por dispositivo); la clase con el dispositivo queda para el CSS personalizado.
		$this->add_responsive_control(
			'alignment',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'default'              => 'center',
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
				'prefix_class'         => 'digi-testimonial-%s-align-',
				'selectors_dictionary' => self::ALIGNMENT,
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
			)
		);

		$this->end_injection();

		$this->start_controls_section(
			'section_skin_style',
			array(
				'label'     => esc_html__( 'Burbuja', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'skin' => 'bubble',
				),
			)
		);

		$this->add_control(
			'background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'alpha'     => false,
				'selectors' => array(
					'{{WRAPPER}} .digi-testimonial__content, {{WRAPPER}} .digi-testimonial__content:after' => 'background-color: {{VALUE}}',
				),
			)
		);

		// Sólo en lo convertido de PRO (lo fija el mapa); vacío, el pico apunta a la imagen en cada dispositivo.
		$this->add_control(
			'pro_bubble',
			array(
				'label'                => esc_html__( 'Pico heredado', 'pro-elements' ),
				'description'          => esc_html__( 'El widget original colocaba el pico sólo con la alineación de escritorio. «Hacia la imagen» lo coloca junto a la imagen en cada dispositivo.', 'pro-elements' ),
				'type'                 => Controls_Manager::SELECT,
				'options'              => array(
					''       => esc_html__( 'Hacia la imagen', 'pro-elements' ),
					'start'  => esc_html__( 'Al inicio', 'pro-elements' ),
					'left'   => esc_html__( 'Izquierda', 'pro-elements' ),
					'center' => esc_html__( 'Centro', 'pro-elements' ),
					'right'  => esc_html__( 'Derecha', 'pro-elements' ),
				),
				'default'              => '',
				'selectors_dictionary' => self::PRO_BUBBLE,
				'selectors'            => array(
					'{{WRAPPER}} .digi-testimonial__content'          => '{{VALUE}}',
					'body.rtl {{WRAPPER}} .digi-testimonial__content' => '--digi-t-l:var(--digi-t-rl);--digi-t-r:var(--digi-t-rr)',
				),
				'condition'            => array( 'pro_bubble!' => '' ),
			)
		);

		$this->add_responsive_control(
			'text_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array(
					'top'    => '20',
					'bottom' => '20',
					'left'   => '20',
					'right'  => '20',
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-testimonial__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
					'{{WRAPPER}}.digi-testimonial--layout-image_left .digi-testimonial__footer, {{WRAPPER}}.digi-testimonial--layout-image_right .digi-testimonial__footer' => 'padding-top: {{TOP}}{{UNIT}}',
					'{{WRAPPER}}.digi-testimonial--layout-image_above .digi-testimonial__footer, {{WRAPPER}}.digi-testimonial--layout-image_inline .digi-testimonial__footer, {{WRAPPER}}.digi-testimonial--layout-image_stacked .digi-testimonial__footer' => 'padding: 0 {{RIGHT}}{{UNIT}} 0 {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-testimonial__content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'border',
			array(
				'label'     => esc_html__( 'Borde', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'selectors' => array(
					'{{WRAPPER}} .digi-testimonial__content, {{WRAPPER}} .digi-testimonial__content:after' => 'border-style: solid',
				),
			)
		);

		$this->add_control(
			'border_color',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#000',
				'selectors' => array(
					'{{WRAPPER}} .digi-testimonial__content'       => 'border-color: {{VALUE}}',
					'{{WRAPPER}} .digi-testimonial__content:after' => 'border-color: transparent {{VALUE}} {{VALUE}} transparent',
				),
				'condition' => array(
					'border' => 'yes',
				),
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
				'selectors'  => array(
					'{{WRAPPER}} .digi-testimonial__content, {{WRAPPER}} .digi-testimonial__content:after' => 'border-width: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.digi-testimonial--layout-image_stacked .digi-testimonial__content:after, {{WRAPPER}}.digi-testimonial--layout-image_inline .digi-testimonial__content:after' => 'margin-top: -{{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.digi-testimonial--layout-image_above .digi-testimonial__content:after' => 'margin-bottom: -{{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'border' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		$this->start_injection(
			array(
				'at' => 'before',
				'of' => 'section_navigation',
			)
		);

		$this->start_controls_section(
			'section_content_style',
			array(
				'label' => esc_html__( 'Contenido', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'content_gap',
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
					'{{WRAPPER}}.digi-testimonial--layout-image_inline .digi-testimonial__footer, {{WRAPPER}}.digi-testimonial--layout-image_stacked .digi-testimonial__footer' => 'margin-top: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.digi-testimonial--layout-image_above .digi-testimonial__footer' => 'margin-bottom: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.digi-testimonial--layout-image_left .digi-testimonial__footer' => 'padding-right: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.digi-testimonial--layout-image_right .digi-testimonial__footer' => 'padding-left: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'content_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-testimonial__text' => 'color: {{VALUE}}',
				),
				'global'    => array(
					'default' => 'globals/colors?id=text',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'content_typography',
				'selector' => '{{WRAPPER}} .digi-testimonial__text',
				'global'   => array(
					'default' => 'globals/typography?id=text',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name'     => 'text_stroke',
				'selector' => '{{WRAPPER}} .digi-testimonial__text',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'content_text_shadow',
				'selector' => '{{WRAPPER}} .digi-testimonial__text',
			)
		);

		$this->add_control(
			'name_title_style',
			array(
				'label'     => esc_html__( 'Nombre', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-testimonial__name' => 'color: {{VALUE}}',
				),
				'global'    => array(
					'default' => 'globals/colors?id=text',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'name_typography',
				'selector' => '{{WRAPPER}} .digi-testimonial__name',
				'global'   => array(
					'default' => 'globals/typography?id=primary',
				),
			)
		);

		$this->add_control(
			'heading_title_style',
			array(
				'label'     => esc_html__( 'Cargo', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-testimonial__title' => 'color: {{VALUE}}',
				),
				'global'    => array(
					'default' => 'globals/colors?id=primary',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .digi-testimonial__title',
				'global'   => array(
					'default' => 'globals/typography?id=secondary',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_image_style',
			array(
				'label' => esc_html__( 'Imagen', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// El pico de la burbuja se coloca con la imagen: las variables `--digi-t-a*` las leen las reglas de la hoja.
		$this->add_responsive_control(
			'image_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 200 ),
					'em'  => array( 'max' => 20 ),
					'rem' => array( 'max' => 20 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-testimonial__image img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}' => '--digi-t-at: calc( {{text_padding.TOP}}{{text_padding.UNIT}} + ({{SIZE}}{{UNIT}} / 2) - 8px ); --digi-t-al: calc( {{text_padding.LEFT}}{{text_padding.UNIT}} + ({{SIZE}}{{UNIT}} / 2) - 8px ); --digi-t-ar: calc( {{text_padding.RIGHT}}{{text_padding.UNIT}} + ({{SIZE}}{{UNIT}} / 2) - 8px ); --digi-t-ia: auto;',
				),
			)
		);

		$this->add_responsive_control(
			'image_gap',
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
					'{{WRAPPER}}' => '--digi-t-gap: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'image_border',
			array(
				'label'     => esc_html__( 'Borde', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'selectors' => array(
					'{{WRAPPER}} .digi-testimonial__image img' => 'border-style: solid',
				),
			)
		);

		$this->add_control(
			'image_border_color',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#000',
				'selectors' => array(
					'{{WRAPPER}} .digi-testimonial__image img' => 'border-color: {{VALUE}}',
				),
				'condition' => array(
					'image_border' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'image_border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 20 ),
					'em'  => array( 'max' => 2 ),
					'rem' => array( 'max' => 2 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-testimonial__image img' => 'border-width: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'image_border' => 'yes',
				),
			)
		);

		$this->add_control(
			'image_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-testimonial__image img' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();

		$this->end_injection();

		// Como PRO: 95 % por defecto (su regla de las flechas no alcanzaba la pista) y, con un ancho elegido y flechas, 40 px menos.
		$this->update_responsive_control(
			'width',
			array(
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-t-w: {{SIZE}}{{UNIT}}; --digi-t-wa: calc({{SIZE}}{{UNIT}} - 40px);',
				),
			)
		);

		$this->update_responsive_control(
			'slides_per_view',
			array(
				'condition' => null,
			)
		);

		$this->update_responsive_control(
			'slides_to_scroll',
			array(
				'condition' => null,
			)
		);

		$this->remove_control( 'effect' );
		$this->remove_responsive_control( 'height' );
		$this->remove_control( 'pagination_position' );
	}

	protected function add_repeater_controls( Repeater $repeater ) {
		$repeater->add_control(
			'content',
			array(
				'label'   => esc_html__( 'Contenido', 'pro-elements' ),
				'type'    => Controls_Manager::TEXTAREA,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'image',
			array(
				'label'   => esc_html__( 'Imagen', 'pro-elements' ),
				'type'    => Controls_Manager::MEDIA,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'name',
			array(
				'label'   => esc_html__( 'Nombre', 'pro-elements' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Nombre Apellido', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
				'ai'      => array(
					'active' => false,
				),
			)
		);

		$repeater->add_control(
			'title',
			array(
				'label'   => esc_html__( 'Cargo', 'pro-elements' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Director general', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
				'ai'      => array(
					'active' => false,
				),
			)
		);
	}

	protected function get_repeater_defaults() {
		$slide = array(
			'content' => esc_html__( 'Escribe aquí lo que opina tu cliente: una experiencia concreta, en sus propias palabras, que ayude a otros a decidirse.', 'pro-elements' ),
			'name'    => esc_html__( 'Nombre Apellido', 'pro-elements' ),
			'title'   => esc_html__( 'Director general', 'pro-elements' ),
			'image'   => array(
				'url' => class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '',
			),
		);
		return array( $slide, $slide, $slide );
	}

	/**
	 * Como PRO en este widget: la paginación de puntos o de fracción reserva 40 px debajo de la pista (la hoja fija
	 * `--digi-crs-pag-space`), y la barra de progreso va arriba sin reservar nada.
	 */
	protected function slider_args( array $settings ): array {
		$args                        = parent::slider_args( $settings );
		$args['pagination_position'] = 'progressbar' === ( $args['pagination'] ?? '' ) ? 'inside' : 'outside';
		return $args;
	}

	protected function print_slider( ?array $settings = null ) {
		if ( null === $settings ) {
			$settings = $this->get_settings_for_display();
		}
		$settings['container_class'] = trim( (string) ( $settings['container_class'] ?? '' ) . ' digi-testimonials' . ( ! empty( $settings['show_arrows'] ) ? ' digi-testimonials--arrows' : '' ) );
		parent::print_slider( $settings );
	}

	/** Disposición efectiva del nombre y el cargo: con la piel «Burbuja», siempre junto a la imagen (como PRO). */
	private function cite_layout( array $settings ): string {
		if ( 'bubble' === ( $settings['skin'] ?? '' ) ) {
			return 'image_inline';
		}
		$layout = (string) ( $settings['layout'] ?? 'image_inline' );
		return in_array( $layout, self::LAYOUTS, true ) ? $layout : 'image_inline';
	}

	/** `<cite>` con el nombre y el cargo (el HTML que permite el contenido de una entrada). */
	private static function cite( array $slide ): string {
		$name  = (string) ( $slide['name'] ?? '' );
		$title = (string) ( $slide['title'] ?? '' );
		if ( '' === $name && '' === $title ) {
			return '';
		}
		$html = '<cite class="digi-testimonial__cite">';
		if ( '' !== $name ) {
			$html .= '<span class="digi-testimonial__name">' . wp_kses_post( $name ) . '</span>';
		}
		if ( '' !== $title ) {
			$html .= '<span class="digi-testimonial__title">' . wp_kses_post( $title ) . '</span>';
		}
		return $html . '</cite>';
	}

	protected function print_slide( array $slide, array $settings, $element_key ) {
		$outside = in_array( $this->cite_layout( $settings ), array( 'image_above', 'image_right', 'image_left' ), true );
		$cite    = self::cite( $slide );
		$content = (string) ( $slide['content'] ?? '' );
		$image   = '';
		if ( ! empty( $slide['image']['url'] ) || ! empty( $slide['image']['id'] ) ) {
			// Texto alternativo como PRO: el de la imagen o, sin él, el nombre.
			$alt   = ! empty( $slide['image']['alt'] ) ? (string) $slide['image']['alt'] : wp_strip_all_tags( (string) ( $slide['name'] ?? '' ) );
			$image = $this->get_slide_image( $slide, $settings, array( 'alt' => $alt ) );
		}
		$id = isset( $slide['_id'] ) && preg_match( '/^[A-Za-z0-9_-]+$/', (string) $slide['_id'] ) ? ' elementor-repeater-item-' . $slide['_id'] : '';

		echo '<div class="digi-testimonial' . esc_attr( $id ) . '">';
		if ( '' !== $content ) {
			echo '<div class="digi-testimonial__content"><div class="digi-testimonial__text">' . wp_kses_post( $content ) . '</div>';
			echo $outside ? $cite : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cite() escapa.
			echo '</div>';
		}
		echo '<div class="digi-testimonial__footer">';
		if ( '' !== $image ) {
			echo '<div class="digi-testimonial__image">' . $image . self::preloader( $settings ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_slide_image() escapa.
		}
		echo $outside ? '' : $cite; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cite() escapa.
		echo '</div></div>';
	}

	public function render_markdown(): string {
		$blocks = array();
		foreach ( (array) ( $this->get_settings_for_display()['slides'] ?? array() ) as $slide ) {
			$lines = array();
			foreach ( array( 'content', 'name', 'title' ) as $key ) {
				$text = trim( html_entity_decode( wp_strip_all_tags( (string) ( $slide[ $key ] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) );
				if ( '' !== $text ) {
					$lines[] = 'content' === $key ? '> ' . str_replace( "\n", "\n> ", $text ) : '— ' . $text;
				}
			}
			if ( $lines ) {
				$blocks[] = implode( "\n", $lines );
			}
		}
		return implode( "\n\n", $blocks );
	}
}
