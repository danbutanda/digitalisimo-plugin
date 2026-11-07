<?php
namespace Digitalisimo\Elements\Carousel\Widgets;

use Digitalisimo\Elements\Carousel\Base_Widget;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Reseñas (`digitalisimo-reviews`): sustituye al widget «Reviews» de PRO (`modules/carousel/widgets/reviews.php`, mapa
 * `modules/digitalisimo-convert/maps/reviews.php`). Repite sus controles con el mismo nombre, valor por defecto y
 * condiciones (y los de `Base_Widget`, con los mismos cambios que PRO); el marcado, las clases y la hoja son propios,
 * sobre el motor `digi-crs`, sin Swiper ni JavaScript propio.
 *
 * Cada reseña: cabecera (enlace si la fila lo tiene) con imagen, nombre, valoración y cargo, y el icono social con su
 * color oficial (variable `--digi-review-icon` en línea; «Personalizado» la sustituye), y el texto debajo.
 *
 * Valoración: en unas Reseñas nuevas, cinco estrellas propias (SVG dibujado para este widget, o los caracteres ★/☆ con
 * «Unicode»), sin la fuente de iconos del núcleo, con la parcial recortada a la décima. En lo convertido de PRO
 * (`legacy_stars()`), los caracteres de eicons que pintaba PRO (`GLYPH`), recortados como su `render_stars()`. El conjunto
 * tiene `role="img"` y el nombre «Valoración: 4,5 de 5»; las estrellas son decorativas.
 *
 * Diferencias declaradas con PRO: el icono social es decorativo (PRO le ponía `aria-label="Read More"` a un `div`), la
 * valoración tiene nombre accesible (PRO leía los caracteres de la fuente de iconos), en unas Reseñas nuevas las estrellas
 * son SVG (las de PRO sólo se veían donde cargaba eicons) y una valoración con más de un decimal se redondea a la décima
 * (PRO pintaba la estrella entera, y lo convertido también), y la imagen se pinta con `wp_get_attachment_image()`.
 */
class Reviews extends Base_Widget {

	/** Estrella llena y vacía (contorno), dibujadas para este widget en una caja de 24 × 24. */
	const STAR         = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 .7l2.9 8 8.5.3-6.7 5.2 2.4 8.2-7.1-4.8-7.1 4.8 2.4-8.2L.6 9l8.5-.3z"/></svg>';
	const STAR_OUTLINE = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M12 .7l2.9 8 8.5.3-6.7 5.2 2.4 8.2-7.1-4.8-7.1 4.8 2.4-8.2L.6 9l8.5-.3zm0 4.4l-1.8 5.1-5.4.2 4.3 3.3-1.6 5.1 4.5-3 4.5 3-1.6-5.1 4.3-3.3-5.4-.2z"/></svg>';

	/**
	 * Estrella llena y vacía de lo convertido de PRO: los caracteres de la fuente de iconos del núcleo (eicons) que pintaba
	 * PRO con «Font Awesome», con `font-family: eicons` y sin encolar la fuente (como PRO): se ven donde el sitio carga
	 * eicons y no se ven donde no la carga (los iconos como SVG en línea, valor por defecto desde 3.17), igual que antes.
	 */
	const GLYPH         = '&#xE934;';
	const GLYPH_OUTLINE = '&#xE933;';

	/** Colores oficiales del icono social (los mismos que PRO), por nombre del icono. */
	const SOCIAL_COLORS = array(
		'android'        => '#a4c639',
		'apple'          => '#999',
		'behance'        => '#1769ff',
		'bitbucket'      => '#205081',
		'codepen'        => '#000',
		'delicious'      => '#39f',
		'digg'           => '#005be2',
		'dribbble'       => '#ea4c89',
		'envelope'       => '#ea4335',
		'facebook'       => '#3b5998',
		'flickr'         => '#0063dc',
		'foursquare'     => '#2d5be3',
		'github'         => '#333',
		'google-plus'    => '#dd4b39',
		'houzz'          => '#7ac142',
		'instagram'      => '#262626',
		'jsfiddle'       => '#487aa2',
		'linkedin'       => '#0077b5',
		'medium'         => '#00ab6b',
		'meetup'         => '#ec1c40',
		'mixcloud'       => '#273a4b',
		'odnoklassniki'  => '#f4731c',
		'pinterest'      => '#bd081c',
		'product-hunt'   => '#da552f',
		'reddit'         => '#ff4500',
		'rss'            => '#f26522',
		'shopping-cart'  => '#4caf50',
		'skype'          => '#00aff0',
		'slideshare'     => '#0077b5',
		'snapchat'       => '#fffc00',
		'soundcloud'     => '#f80',
		'spotify'        => '#2ebd59',
		'stack-overflow' => '#fe7a15',
		'steam'          => '#00adee',
		'stumbleupon'    => '#eb4924',
		'telegram'       => '#2ca5e0',
		'thumb-tack'     => '#1aa1d8',
		'thumbtack'      => '#1aa1d8',
		'threads'        => '#000',
		'tripadvisor'    => '#589442',
		'tumblr'         => '#35465c',
		'twitch'         => '#6441a5',
		'twitter'        => '#1da1f2',
		'vimeo'          => '#1ab7ea',
		'vk'             => '#45668e',
		'weibo'          => '#dd2430',
		'weixin'         => '#31a918',
		'whatsapp'       => '#25d366',
		'wordpress'      => '#21759b',
		'x'              => '#000',
		'xing'           => '#026466',
		'yelp'           => '#af0606',
		'youtube'        => '#cd201f',
		'500px'          => '#0099e5',
	);

	public function get_name() {
		return 'digitalisimo-reviews';
	}

	/** Como PRO (su script de testimonios, que usan también las reseñas): una visible en cada dispositivo con «Predeterminado». */
	protected function slides_per_view_defaults(): array {
		return array( 'desktop' => '1' );
	}

	public function get_title() {
		return esc_html__( 'Reseñas', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-review';
	}

	public function get_keywords() {
		return array( 'reseñas', 'reviews', 'opiniones', 'valoración', 'estrellas', 'social', 'testimonios', 'carrusel' );
	}

	protected function register_controls() {
		parent::register_controls();

		$this->update_control(
			'slide_padding',
			array(
				'selectors' => array(
					'{{WRAPPER}} .digi-review__header'  => 'padding-top: {{TOP}}{{UNIT}}; padding-left: {{LEFT}}{{UNIT}}; padding-right: {{RIGHT}}{{UNIT}};',
					'{{WRAPPER}} .digi-review__content' => 'padding-bottom: {{BOTTOM}}{{UNIT}}; padding-left: {{LEFT}}{{UNIT}}; padding-right: {{RIGHT}}{{UNIT}};',
				),
			)
		);

		$this->start_injection( array( 'of' => 'slide_padding' ) );

		$this->add_control(
			'heading_header',
			array(
				'label'     => esc_html__( 'Cabecera', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'header_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-review__header' => 'background-color: {{VALUE}}',
				),
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
					'{{WRAPPER}} .digi-review__header'  => 'padding-block-end: calc( {{SIZE}}{{UNIT}} / 2 )',
					'{{WRAPPER}} .digi-review__content' => 'padding-block-start: calc( {{SIZE}}{{UNIT}} / 2 )',
				),
			)
		);

		$this->add_control(
			'show_separator',
			array(
				'label'        => esc_html__( 'Separador', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_off'    => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'     => esc_html__( 'Mostrar', 'pro-elements' ),
				'default'      => 'has-separator',
				'return_value' => 'has-separator',
				'prefix_class' => 'digi-review--',
				'separator'    => 'before',
			)
		);

		$this->add_control(
			'separator_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-review__header' => 'border-block-end-color: {{VALUE}}',
				),
				'condition' => array(
					'show_separator!' => '',
				),
			)
		);

		$this->add_control(
			'separator_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 20 ),
					'em'  => array( 'max' => 2 ),
					'rem' => array( 'max' => 2 ),
				),
				'condition'  => array(
					'show_separator!' => '',
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-review__header' => 'border-block-end-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_injection();

		$this->start_injection(
			array(
				'at' => 'before',
				'of' => 'section_navigation',
			)
		);

		$this->start_controls_section(
			'section_content_style',
			array(
				'label' => esc_html__( 'Texto', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'name_title_style',
			array(
				'label' => esc_html__( 'Nombre', 'pro-elements' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-review__name' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'name_typography',
				'selector' => '{{WRAPPER}} .digi-review__header, {{WRAPPER}} .digi-review__name',
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
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-review__title' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .digi-review__title',
			)
		);

		$this->add_control(
			'heading_review_style',
			array(
				'label'     => esc_html__( 'Reseña', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'content_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-review__text' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'content_typography',
				'selector' => '{{WRAPPER}} .digi-review__text',
				'global'   => array(
					'default' => 'globals/typography?id=text',
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

		$this->add_control(
			'image_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-review__image img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}',
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
					'{{WRAPPER}} .digi-review__image + .digi-review__cite' => 'margin-inline-start: {{SIZE}}{{UNIT}}; margin-inline-end: 0;',
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
					'{{WRAPPER}} .digi-review__image img' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_icon_style',
			array(
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'   => esc_html__( 'Color', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'default',
				'options' => array(
					'default' => esc_html__( 'Oficial', 'pro-elements' ),
					'custom'  => esc_html__( 'Personalizado', 'pro-elements' ),
				),
			)
		);

		$this->add_control(
			'icon_custom_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'icon_color' => 'custom',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-review__icon'     => 'color: {{VALUE}};',
					'{{WRAPPER}} .digi-review__icon svg' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-review__icon'     => 'font-size: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-review__icon svg' => 'width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_rating_style',
			array(
				'label' => esc_html__( 'Valoración', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Las claves de las opciones son las de PRO; «SVG» es la estrella propia (PRO usaba una fuente de iconos).
		$this->add_control(
			'star_style',
			array(
				'label'        => esc_html__( 'Icono', 'pro-elements' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'star_fontawesome' => esc_html__( 'Estrella (SVG)', 'pro-elements' ),
					'star_unicode'     => 'Unicode',
				),
				'default'      => 'star_fontawesome',
				'render_type'  => 'template',
				'prefix_class' => 'digi-review--star-style-',
				'separator'    => 'before',
			)
		);

		// Sólo en lo convertido de PRO (lo fija el mapa): las estrellas de la fuente de iconos del núcleo (`GLYPH`).
		$this->add_control(
			'star_icon',
			array(
				'type'    => Controls_Manager::HIDDEN,
				'default' => '',
			)
		);

		$this->add_control(
			'unmarked_star_style',
			array(
				'label'   => esc_html__( 'Estilo sin marcar', 'pro-elements' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'solid'   => array(
						'title' => esc_html__( 'Relleno', 'pro-elements' ),
						'icon'  => 'eicon-star',
					),
					'outline' => array(
						'title' => esc_html__( 'Contorno', 'pro-elements' ),
						'icon'  => 'eicon-star-o',
					),
				),
				'default' => 'solid',
			)
		);

		$this->add_control(
			'star_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-review__stars' => 'font-size: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->add_control(
			'star_space',
			array(
				'label'      => esc_html__( 'Espaciado', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 50 ),
					'em'  => array( 'max' => 5 ),
					'rem' => array( 'max' => 5 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-review__star:not(:last-of-type)' => 'margin-inline-end: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'stars_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-review__fill' => 'color: {{VALUE}}',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'stars_unmarked_color',
			array(
				'label'     => esc_html__( 'Color sin marcar', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-review__star' => 'color: {{VALUE}}',
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

		// Como PRO (`update_control`, no la versión responsiva): sólo el valor de escritorio deja de depender del efecto.
		$this->update_control(
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
			'image',
			array(
				'label'   => esc_html__( 'Imagen', 'pro-elements' ),
				'type'    => Controls_Manager::MEDIA,
				'default' => array(
					'url' => class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '',
				),
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
				'default' => '@usuario',
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'rating',
			array(
				'label'   => esc_html__( 'Valoración', 'pro-elements' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 5,
				'step'    => 0.1,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'selected_social_icon',
			array(
				'label'            => esc_html__( 'Icono', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'social_icon',
				'default'          => array(
					'value'   => 'fab fa-twitter',
					'library' => 'fa-brands',
				),
				'recommended'      => array(
					'fa-solid'  => array( 'rss', 'shopping-cart', 'thumbtack' ),
					'fa-brands' => array( 'android', 'apple', 'behance', 'bitbucket', 'codepen', 'delicious', 'digg', 'dribbble', 'envelope', 'facebook', 'flickr', 'foursquare', 'github', 'google-plus', 'houzz', 'instagram', 'jsfiddle', 'linkedin', 'medium', 'meetup', 'mix', 'mixcloud', 'odnoklassniki', 'pinterest', 'product-hunt', 'reddit', 'skype', 'slideshare', 'snapchat', 'soundcloud', 'spotify', 'stack-overflow', 'steam', 'telegram', 'threads', 'tripadvisor', 'tumblr', 'twitch', 'twitter', 'vimeo', 'fa-vk', 'weibo', 'weixin', 'whatsapp', 'wordpress', 'x-twitter', 'xing', 'yelp', 'youtube', '500px' ),
				),
			)
		);

		$repeater->add_control(
			'link',
			array(
				'label'   => esc_html__( 'Enlace', 'pro-elements' ),
				'type'    => Controls_Manager::URL,
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'content',
			array(
				'label'   => esc_html__( 'Reseña', 'pro-elements' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => esc_html__( 'Escribe aquí la reseña: qué le gustó a tu cliente y por qué lo recomendaría, en sus propias palabras.', 'pro-elements' ),
				'dynamic' => array(
					'active' => true,
				),
			)
		);
	}

	protected function get_repeater_defaults() {
		$slide = array(
			'content' => esc_html__( 'Escribe aquí la reseña: qué le gustó a tu cliente y por qué lo recomendaría, en sus propias palabras.', 'pro-elements' ),
			'name'    => esc_html__( 'Nombre Apellido', 'pro-elements' ),
			'title'   => '@usuario',
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
		$settings['container_class'] = trim( (string) ( $settings['container_class'] ?? '' ) . ' digi-reviews' . ( ! empty( $settings['show_arrows'] ) ? ' digi-reviews--arrows' : '' ) );
		parent::print_slider( $settings );
	}

	/**
	 * Cinco estrellas de una valoración (0–5, a la décima). `$unicode` usa ★/☆; `$glyph`, los caracteres de eicons de PRO
	 * (`GLYPH`, lo convertido); si no, la estrella propia. Devuelve '' sin valoración (como PRO, 0 no pinta estrellas).
	 */
	public static function stars( $rating, bool $unicode = false, bool $outline = false, bool $glyph = false ): string {
		if ( ! is_numeric( $rating ) || (float) $rating <= 0 ) {
			return '';
		}
		$glyph  = $glyph && ! $unicode;
		$tenths = (int) round( min( 5, (float) $rating ) * 10 );
		$base   = $unicode ? ( $outline ? '&#9734;' : '&#9733;' ) : ( $glyph ? ( $outline ? self::GLYPH_OUTLINE : self::GLYPH ) : ( $outline ? self::STAR_OUTLINE : self::STAR ) );
		$fill   = $unicode ? '&#9733;' : ( $glyph ? self::GLYPH : self::STAR );
		/* translators: %s: valoración con un decimal, p. ej. 4,5. */
		$label = sprintf( __( 'Valoración: %s de 5', 'pro-elements' ), function_exists( 'number_format_i18n' ) ? number_format_i18n( $tenths / 10, 0 === $tenths % 10 ? 0 : 1 ) : (string) ( $tenths / 10 ) );
		$html  = '<div class="digi-review__stars' . ( $glyph ? ' digi-review__stars--eicons' : '' ) . '" role="img" aria-label="' . esc_attr( $label ) . '">';
		if ( $glyph ) {
			// Como `render_stars()` de PRO, con el valor tal como se guardó: la parcial es la fracción × 10 y sólo recorta si es
			// una décima entera (con más decimales, o con un entero guardado como texto, que su comparación estricta no
			// reconoce, la clase de PRO no tenía regla y la estrella salía entera).
			$value = (float) $rating > 5 ? 5 : $rating;
			$whole = (int) $value;
			$part  = (string) ( ( $value - $whole ) * 10 );
			for ( $star = 1; $star <= 5; $star++ ) {
				if ( $star <= $whole ) {
					$html .= '<i class="digi-review__star digi-review__star--full">' . $base . '<span class="digi-review__fill">' . $fill . '</span></i>';
				} elseif ( $whole + 1 === $star && $value !== $whole ) {
					$cut   = in_array( $part, array( '1', '2', '3', '4', '5', '6', '7', '8', '9' ), true );
					$html .= '<i class="digi-review__star digi-review__star--' . ( $cut ? $part : 'full' ) . '">' . $base . '<span class="digi-review__fill"' . ( $cut ? ' style="width:' . ( (int) $part * 10 ) . '%"' : '' ) . '>' . $fill . '</span></i>';
				} else {
					$html .= '<i class="digi-review__star digi-review__star--empty">' . $base . '</i>';
				}
			}
			return $html . '</div>';
		}
		for ( $star = 1; $star <= 5; $star++ ) {
			$part = max( 0, min( 10, $tenths - ( $star - 1 ) * 10 ) );
			if ( 0 === $part ) {
				$html .= '<i class="digi-review__star digi-review__star--empty">' . $base . '</i>';
			} elseif ( 10 === $part ) {
				$html .= '<i class="digi-review__star digi-review__star--full">' . $base . '<span class="digi-review__fill">' . $fill . '</span></i>';
			} else {
				$html .= '<i class="digi-review__star digi-review__star--' . $part . '">' . $base . '<span class="digi-review__fill" style="width:' . ( $part * 10 ) . '%">' . $fill . '</span></i>';
			}
		}
		return $html . '</div>';
	}

	/**
	 * ¿Estrellas de PRO (`GLYPH`)? Las fija el mapa en lo convertido (`star_icon` = `legacy`); sin el control, lo
	 * convertido antes de existir (marca `_digitalisimo_pro`, que el traductor ya no vuelve a tocar). Unas Reseñas nuevas,
	 * la estrella propia.
	 */
	public static function legacy_stars( array $s ): bool {
		$icon = (string) ( $s['star_icon'] ?? '' );
		return 'legacy' === $icon || ( '' === $icon && 'reviews' === (string) ( $s['_digitalisimo_pro'] ?? '' ) );
	}

	/** Nombre del icono social (para su color oficial), como lo deduce PRO de la clase del icono. */
	public static function social_name( array $slide ): string {
		$icon = (array) ( $slide['selected_social_icon'] ?? array() );
		if ( ! empty( $slide['social_icon'] ) && empty( $slide['__fa4_migrated']['selected_social_icon'] ) ) {
			return str_replace( 'fa fa-', '', (string) $slide['social_icon'] );
		}
		if ( 'svg' === ( $icon['library'] ?? '' ) || ! is_string( $icon['value'] ?? null ) ) {
			return '';
		}
		$parts = explode( ' ', $icon['value'], 2 );
		return empty( $parts[1] ) ? '' : str_replace( 'fa-', '', $parts[1] );
	}

	/** Icono social (decorativo) con su color oficial en `--digi-review-icon`. */
	private function social_icon( array $slide ): string {
		$legacy = ! empty( $slide['social_icon'] ) && empty( $slide['__fa4_migrated']['selected_social_icon'] );
		if ( $legacy ) {
			$icon = '<i class="' . esc_attr( (string) $slide['social_icon'] ) . '" aria-hidden="true"></i>';
		} elseif ( ! empty( $slide['selected_social_icon']['value'] ) && class_exists( '\Elementor\Icons_Manager' ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $slide['selected_social_icon'], array( 'aria-hidden' => 'true' ) );
			$icon = (string) ob_get_clean();
		} else {
			return '';
		}
		if ( '' === trim( $icon ) ) {
			return '';
		}
		$name  = self::social_name( $slide );
		$class = 'digi-review__icon' . ( '' !== $name && preg_match( '/^[a-z0-9-]+$/', $name ) ? ' digi-review__icon--' . $name : '' );
		$style = isset( self::SOCIAL_COLORS[ $name ] ) ? ' style="--digi-review-icon:' . self::SOCIAL_COLORS[ $name ] . '"' : '';
		return '<div class="' . esc_attr( $class ) . '"' . $style . ' aria-hidden="true">' . $icon . '</div>';
	}

	protected function print_slide( array $slide, array $settings, $element_key ) {
		$name    = (string) ( $slide['name'] ?? '' );
		$title   = (string) ( $slide['title'] ?? '' );
		$content = (string) ( $slide['content'] ?? '' );
		$has_img = ! empty( $slide['image']['url'] ) || ! empty( $slide['image']['id'] );
		$id      = isset( $slide['_id'] ) && preg_match( '/^[A-Za-z0-9_-]+$/', (string) $slide['_id'] ) ? (string) $slide['_id'] : '';

		echo '<div class="digi-review' . ( '' !== $id ? ' elementor-repeater-item-' . esc_attr( $id ) : '' ) . '">';
		if ( $has_img || '' !== $name || '' !== $title ) {
			$key = 'digi_review_header_' . $element_key;
			$this->add_render_attribute( $key, 'class', 'digi-review__header' );
			$tag = empty( $slide['link']['url'] ) ? 'div' : 'a';
			if ( 'a' === $tag ) {
				$this->add_link_attributes( $key, $slide['link'] );
			}
			echo '<' . $tag . ' ' . $this->get_render_attribute_string( $key ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Etiqueta fija y atributos escapados por el núcleo.
			$image = $has_img ? $this->get_slide_image( $slide, $settings ) : '';
			if ( '' !== $image ) {
				echo '<div class="digi-review__image">' . $image . self::preloader( $settings ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_slide_image() escapa.
			}
			if ( '' !== $name || '' !== $title ) {
				echo '<cite class="digi-review__cite">';
				if ( '' !== $name ) {
					echo '<span class="digi-review__name">' . wp_kses_post( $name ) . '</span>';
				}
				echo self::stars( $slide['rating'] ?? '', 'star_unicode' === ( $settings['star_style'] ?? '' ), 'outline' === ( $settings['unmarked_star_style'] ?? '' ), self::legacy_stars( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Marcado propio.
				if ( '' !== $title ) {
					echo '<span class="digi-review__title">' . wp_kses_post( $title ) . '</span>';
				}
				echo '</cite>';
			}
			echo $this->social_icon( $slide ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icono del núcleo o clase escapada.
			echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Etiqueta fija.
		}
		if ( '' !== $content ) {
			echo '<div class="digi-review__content"><div class="digi-review__text">' . wp_kses_post( $content ) . '</div></div>';
		}
		echo '</div>';
	}

	public function render_markdown(): string {
		$blocks = array();
		foreach ( (array) ( $this->get_settings_for_display()['slides'] ?? array() ) as $slide ) {
			$plain = static function ( $value ) {
				return trim( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ) );
			};
			$lines = array();
			$head  = trim( $plain( $slide['name'] ?? '' ) . ( '' !== $plain( $slide['title'] ?? '' ) ? ' (' . $plain( $slide['title'] ) . ')' : '' ) );
			if ( '' !== $head ) {
				$lines[] = '**' . $head . '**';
			}
			if ( is_numeric( $slide['rating'] ?? null ) && (float) $slide['rating'] > 0 ) {
				$lines[] = '- ' . esc_html__( 'Valoración', 'pro-elements' ) . ': ' . min( 5, (float) $slide['rating'] ) . '/5';
			}
			if ( '' !== $plain( $slide['content'] ?? '' ) ) {
				$lines[] = $plain( $slide['content'] );
			}
			if ( $lines ) {
				$blocks[] = implode( "\n", $lines );
			}
		}
		return implode( "\n\n", $blocks );
	}
}
