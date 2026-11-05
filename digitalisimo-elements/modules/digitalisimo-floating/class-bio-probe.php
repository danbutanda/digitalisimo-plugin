<?php
namespace Digitalisimo\Elements\Floating;

use Elementor\Controls_Manager;
use Elementor\Core\Base\Providers\Social_Network_Provider;
use Elementor\Group_Control_Typography;
use Elementor\Modules\LinkInBio\Base\Widget_Link_In_Bio_Base;
use Elementor\Shapes;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Sonda del Link in Bio (C8): construye los controles de una presentación sobre la base del núcleo
 * (`Widget_Link_In_Bio_Base`). La configuración y las secciones de cada presentación repiten las de las variantes de PRO
 * `link-in-bio-var-<n>` (código GPL de PRO Elements, `modules/link-in-bio/widgets/link-in-bio-var-*.php`, con los textos
 * en español): 2 «Classic», 3 «Showcase», 4 «Links», 5 «Services», 6 «Portfolio Bio» y 7 «Business Card».
 */
class Bio_Probe extends Widget_Link_In_Bio_Base {
	use Probe;

	const FAMILY = 'link-in-bio';

	/** Presentaciones, en el orden del control `presentation`. */
	const PRESENTATIONS = array( '2', '3', '4', '5', '6', '7' );

	public static function get_configuration() {
		return self::configure( parent::get_configuration(), '' === self::presentation() ? '2' : self::presentation() );
	}

	/** Configuración de la base del núcleo con los cambios de cada variante de PRO. */
	public static function configure( array $config, string $presentation ): array {
		if ( '2' === $presentation ) {
			$config['content']['bio_section']['description']['default'] = '';
			$config['content']['identity_section']['has_heading_text'] = esc_html__( 'Perfil', 'pro-elements' );
			$config['content']['identity_section']['has_profile_image_controls'] = true;
			$config['content']['identity_section']['identity_image_style'] = false;
			$config['content']['cta_section']['cta_repeater_defaults'] = [
				[
					'cta_link_text' => esc_html__( 'Vive sano', 'pro-elements' ),
				],
				[
					'cta_link_text' => esc_html__( 'Las 10 mejores recetas', 'pro-elements' ),
				],
				[
					'cta_link_text' => esc_html__( 'Preparación de comidas', 'pro-elements' ),
				],
			];
			$config['style']['cta_section']['has_corners']['default'] = 'sharp';
			$config['style']['identity_section']['has_profile_image_shape'] = false;
			$config['style']['identity_section']['profile_image_max'] = 200;

		}
		if ( '3' === $presentation ) {
			$config['content']['cta_section']['cta_has_image'] = true;
			array_pop( $config['content']['cta_section']['cta_repeater_defaults'] );
			$config['style']['cta_section']['has_link_type'] = false;
			$config['style']['cta_section']['has_corners'] = false;
			$config['style']['cta_section']['has_padding'] = false;
			$config['style']['cta_section']['has_border_control']['show_border_args']['condition'] = [];
			$config['style']['cta_section']['has_border_control']['border_width_args']['condition'] = [];
			$config['style']['cta_section']['has_border_control']['border_color_args']['condition'] = [];

		}
		if ( '4' === $presentation ) {
			$config['content']['bio_section']['title']['default'] = '';
			$config['content']['identity_section']['identity_image_style'] = false;
			$config['content']['identity_section']['has_heading_text'] = esc_html__( 'Perfil', 'pro-elements' );
			$config['style']['cta_section']['has_link_type'] = false;
			$config['style']['cta_section']['has_corners'] = false;
			$config['style']['cta_section']['has_padding'] = false;
			$config['style']['cta_section']['has_dividers'] = true;
			$config['style']['cta_section']['has_border_control'] = false;
			$config['style']['cta_section']['has_background_control'] = false;
			$config['style']['cta_section']['has_cta_control_text'] = esc_html__( 'Enlaces', 'pro-elements' );
			$config['style']['border_section']['field_options'] = [
				'image' => [
					'default' => [
						'url' => Utils::get_placeholder_image_src(),
					],
				],
			];
			$config['content']['cta_section']['cta_repeater_defaults'] = [
				[
					'cta_link_text' => esc_html__( 'Vive sano', 'pro-elements' ),
				],
				[
					'cta_link_text' => esc_html__( 'Las 10 mejores recetas', 'pro-elements' ),
				],
				[
					'cta_link_text' => esc_html__( 'Preparación de comidas', 'pro-elements' ),
				],
				[
					'cta_link_text' => esc_html__( 'Recursos saludables', 'pro-elements' ),
				],
			];

		}
		if ( '5' === $presentation ) {
			$config['content']['cta_section']['cta_has_image'] = true;
			$config['content']['bio_section']['description']['default'] = '';
			$config['content']['identity_section']['identity_image_style']['default'] = 'cover';
			$config['content']['cta_section']['cta_repeater_defaults'] = [
				[
					'cta_link_text' => esc_html__( 'Vive sano', 'pro-elements' ),
				],
				[
					'cta_link_text' => esc_html__( 'Mejores recetas', 'pro-elements' ),
				],
				[
					'cta_link_text' => esc_html__( 'Preparación de comidas', 'pro-elements' ),
				],
				[
					'cta_link_text' => esc_html__( 'Recursos', 'pro-elements' ),
				],
			];
			$config['style']['cta_section']['has_link_type'] = false;
			$config['style']['cta_section']['has_corners'] = false;
			$config['style']['cta_section']['has_padding'] = false;
			$config['style']['cta_section']['has_border_control']['show_border_args']['condition'] = [];
			$config['style']['cta_section']['has_border_control']['border_width_args']['condition'] = [];
			$config['style']['cta_section']['has_border_control']['border_color_args']['condition'] = [];

		}
		if ( '6' === $presentation ) {
			$config['content']['cta_section'] = [];
			$config['content']['image_links_section'] = [
				'images_max' => 0,
				'images_repeater_defaults' => [
					[],
					[],
					[],
					[],
				],
			];
			$config['style']['cta_section'] = false;
			$config['style']['image_links_section'] = [
				'has_border_control' => [
					'prefix' => 'image_links',
					'show_border_args' => [
						'label' => esc_html__( 'Borde de la imagen', 'pro-elements' ),
					],
					'border_width_args' => [
						'selectors' => [
							'{{WRAPPER}} .e-link-in-bio' => '--e-link-in-bio-image-links-border-width: {{SIZE}}{{UNIT}}',
						],
					],
					'border_color_args' => [
						'selectors' => [
							'{{WRAPPER}} .e-link-in-bio' => '--e-link-in-bio-image-links-border-color: {{VALUE}}',
						],
					],
				],
			];

		}
		if ( '7' === $presentation ) {
			$config['content']['identity_section']['identity_image_style'] = false;
			$config['content']['identity_section']['has_profile_image_controls'] = true;
			$config['content']['identity_section']['has_heading_text'] = esc_html__( 'Perfil', 'pro-elements' );
			$config['content']['bio_section']['description']['default'] = esc_html__(
				'I combine my love of food and storytelling to create beautiful photos.',
				'pro-elements'
			);
			$config['content']['icon_section']['platform']['group-1'][] = Social_Network_Provider::URL;
			$config['content']['icon_section']['platform']['limit'] = 0;
			$config['content']['icon_section']['default'] = self::p7_default_icons();
			$config['content']['icon_section']['has_text'] = true;
			$config['content']['bio_section']['has_about_field'] = true;
			$config['content']['bio_section']['description']['position'] = 'bottom';
			$config['style']['identity_section']['has_profile_image_shape'] = false;

			$config['style']['cta_section']['has_link_type'] = false;
			$config['style']['cta_section']['has_corners']['default'] = 'round';
			$config['style']['cta_section']['has_padding'] = true;
			$config['style']['cta_section']['has_border_control']['label'] = esc_html__( 'Borde de la imagen', 'pro-elements' );
			$config['style']['cta_section']['has_border_control']['show_border_args'] = [];
			$config['style']['cta_section']['has_border_control']['border_width_args'] = [];
			$config['style']['cta_section']['has_border_control']['border_color_args'] = [];

		}
		return $config;
	}

	/** Posición de la descripción (la presentación 7 la pinta al pie, como su variante de PRO). */
	public function get_description_position() {
		return '7' === self::presentation() ? 'bottom' : parent::get_description_position();
	}

	protected function register_controls(): void {
		parent::register_controls();
		if ( '7' === self::presentation() ) {
			$this->start_injection(
				array(
					'type' => 'section',
					'at'   => 'start',
					'of'   => 'icons_section',
				)
			);
			$this->add_icons_per_row_control();
			$this->end_injection();
		}
	}

	protected function add_content_tab(): void {
		if ( in_array( self::presentation(), array( '2', '4' ), true ) ) {
			$this->add_identity_section();
			$this->add_bio_section();
			$this->add_cta_controls();
			$this->add_icons_controls();
			return;
		}
		parent::add_content_tab();
	}

	protected function add_style_tab(): void {
		if ( in_array( self::presentation(), array( '2', '4' ), true ) ) {
			$this->add_style_identity_controls();
			$this->add_style_bio_controls();
			$this->add_style_cta_section();
			$this->add_style_icons_controls();
			$this->add_style_background_controls();
			return;
		}
		parent::add_style_tab();
	}

	protected function add_cta_controls() {
		if ( '7' === self::presentation() ) {
			$this->p7_cta_controls();
			return;
		}
		parent::add_cta_controls();
	}

	protected function add_style_identity_controls(): void {
		if ( '2' === self::presentation() ) {
			$this->p2_style_identity_controls();
			return;
		}
		if ( '7' === self::presentation() ) {
			$this->p7_style_identity_controls();
			return;
		}
		parent::add_style_identity_controls();
	}

	protected function add_style_bio_controls(): void {
		if ( '7' === self::presentation() ) {
			$this->p7_style_bio_controls();
			return;
		}
		parent::add_style_bio_controls();
	}

	protected function add_style_icons_controls(): void {
		if ( '7' === self::presentation() ) {
			$this->p7_style_icons_controls();
			return;
		}
		parent::add_style_icons_controls();
	}

	protected function p2_style_identity_controls(): void {
		$this->start_controls_section(
			'identity_section_style',
			[
				'label' => esc_html__( 'Identidad', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'identity_section_style_cover_heading',
			[
				'label' => esc_html__( 'Portada', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'none',
			]
		);

		$this->add_identity_image_cover_control( [] );

		$this->add_control(
			'identity_section_style_profile_heading',
			[
				'label' => esc_html__( 'Perfil', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_identity_image_profile_controls( [] );

		$this->end_controls_section();
	}

	protected function p7_cta_controls() {
		$this->start_controls_section(
			'cta_section',
			[
				'label' => esc_html__( 'Botón de acción', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'cta_section_button_text',
			[
				'label' => esc_html__( 'Texto del botón', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Descargar currículum', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
			]
		);

		$this->add_control(
			'cta_link_type',
			[
				'label' => esc_html__( 'Tipo de enlace', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'groups' => [

					[
						'label' => '',
						'options' => Social_Network_Provider::get_social_networks_text(
							[
								Social_Network_Provider::FILE_DOWNLOAD,
								Social_Network_Provider::URL,
							]
						),
					],
					[
						'label' => '   --',
						'options' => Social_Network_Provider::get_social_networks_text(
							[
								Social_Network_Provider::EMAIL,
								Social_Network_Provider::TELEPHONE,
								Social_Network_Provider::MESSENGER,
								Social_Network_Provider::WAZE,
								Social_Network_Provider::WHATSAPP,
							]
						),
					],
				],
				'default' => Social_Network_Provider::FILE_DOWNLOAD,
			],
		);

		$this->add_control(
			'cta_section_alert',
			[
				'type' => Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content' => esc_html__( 'Sube tu tarjeta en formato .vcf', 'pro-elements' ),
				'condition' => [
					'cta_link_type' => Social_Network_Provider::VCF,
				],
			]
		);

		$this->add_control(
			'cta_link_file',
			[
				'label' => esc_html__( 'Elegir archivo', 'pro-elements' ),
				'type' => Controls_Manager::MEDIA,
				'label_block' => true,
				'media_type' => [ 'application/pdf' ],
				'condition' => [
					'cta_link_type' => Social_Network_Provider::FILE_DOWNLOAD,
				],
				'ai' => [
					'active' => false,
				],
			],
		);

		$this->add_control(
			'cta_link_url',
			[
				'label' => esc_html__( 'Enlace', 'pro-elements' ),
				'type' => Controls_Manager::URL,
				'dynamic' => [
					'active' => true,
				],
				'autocomplete' => true,
				'label_block' => true,
				'condition' => [
					'cta_link_type' => [
						Social_Network_Provider::URL,
					],
				],
				'placeholder' => esc_html__( 'Escribe tu enlace', 'pro-elements' ),
				'default' => [
					'is_external' => true,
				],
			],
		);

		$this->add_control(
			'cta_link_mail',
			[
				'label' => esc_html__( 'Correo electrónico', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'label_block' => true,
				'condition' => [
					'cta_link_type' => [
						Social_Network_Provider::EMAIL,
					],
				],
				'placeholder' => esc_html__( 'Escribe tu correo', 'pro-elements' ),
			],
		);

		$this->add_control(
			'cta_link_mail_subject',
			[
				'label' => esc_html__( 'Asunto', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'label_block' => true,
				'condition' => [
					'cta_link_type' => [
						Social_Network_Provider::EMAIL,
					],
				],
				'placeholder' => esc_html__( 'Asunto', 'pro-elements' ),
			],
		);

		$this->add_control(
			'cta_link_mail_body',
			[
				'label' => esc_html__( 'Mensaje', 'pro-elements' ),
				'type' => Controls_Manager::TEXTAREA,
				'dynamic' => [
					'active' => true,
				],
				'label_block' => true,
				'condition' => [
					'cta_link_type' => [
						Social_Network_Provider::EMAIL,
					],
				],
				'placeholder' => esc_html__( 'Mensaje', 'pro-elements' ),
			],
		);

		$this->add_control(
			'cta_link_number',
			[
				'label' => esc_html__( 'Número', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'label_block' => true,
				'condition' => [
					'cta_link_type' => [
						Social_Network_Provider::TELEPHONE,
						Social_Network_Provider::WHATSAPP,
					],
				],
				'placeholder' => esc_html__( 'Escribe tu número', 'pro-elements' ),
			],
		);

		$this->add_control(
			'cta_link_location',
			[
				'label' => esc_html__( 'Ubicación', 'pro-elements' ),
				'type' => Controls_Manager::URL,
				'dynamic' => [
					'active' => true,
				],
				'default' => [
					'is_external' => true,
				],
				'label_block' => true,
				'condition' => [
					'cta_link_type' => [
						Social_Network_Provider::WAZE,
					],
				],
				'placeholder' => esc_html__( 'Pega el enlace de Waze', 'pro-elements' ),
			],
		);

		$this->add_control(
			'cta_link_username',
			[
				'label' => esc_html__( 'Nombre de usuario', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'label_block' => true,
				'condition' => [
					'cta_link_type' => [
						Social_Network_Provider::MESSENGER,
					],
				],
				'placeholder' => esc_html__( 'Escribe tu nombre de usuario', 'pro-elements' ),
			],
		);

		$this->end_controls_section();
	}

	protected function p7_style_identity_controls(): void {
		$this->start_controls_section(
			'identity_section_style',
			[
				'label' => esc_html__( 'Identidad', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'identity_section_style_cover_heading',
			[
				'label' => esc_html__( 'Portada', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'none',
			]
		);

		$this->add_identity_image_cover_control( [] );

		$this->add_control(
			'identity_section_style_profile_heading',
			[
				'label' => esc_html__( 'Perfil', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_identity_image_profile_controls( [] );

		$this->add_control(
			'identity_section_style_divider_heading',
			[
				'label' => esc_html__( 'Divisor de forma', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$shapes_options = [
			'' => esc_html__( 'Ninguno', 'pro-elements' ),
		];

		foreach ( Shapes::get_shapes() as $shape_name => $shape_props ) {
			$shapes_options[ $shape_name ] = $shape_props['title'];
		}

		$shape_divider_side = 'bottom';
		$shape_divider_base_control_key = "identity_section_style_cover_divider_$shape_divider_side";

		$this->add_control(
			$shape_divider_base_control_key,
			[
				'label' => esc_html__( 'Tipo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $shapes_options,
			]
		);

		$this->add_control(
			$shape_divider_base_control_key . '_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'condition' => [
					"$shape_divider_base_control_key!" => '',
				],
				'selectors' => [
					"{{WRAPPER}} .e-link-in-bio__identity-image .elementor-shape-$shape_divider_side .elementor-shape-fill" => 'fill: {{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			$shape_divider_base_control_key . '_width',
			[
				'label' => esc_html__( 'Ancho', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ '%', 'vw', 'custom' ],
				'default' => [
					'unit' => '%',
				],
				'tablet_default' => [
					'unit' => '%',
				],
				'mobile_default' => [
					'unit' => '%',
				],
				'range' => [
					'%' => [
						'min' => 100,
						'max' => 300,
						'step' => 1,
					],
					'vw' => [
						'min' => 100,
						'max' => 300,
						'step' => 1,
					],
				],
				'condition' => [
					"$shape_divider_base_control_key" => array_keys( Shapes::filter_shapes( 'height_only', Shapes::FILTER_EXCLUDE ) ),
				],
				'selectors' => [
					"{{WRAPPER}} .e-link-in-bio__identity-image .elementor-shape-$shape_divider_side svg" => 'width: calc({{SIZE}}{{UNIT}} + 1.3px)',
				],
			]
		);

		$this->add_responsive_control(
			$shape_divider_base_control_key . '_height',
			[
				'label' => esc_html__( 'Alto', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'max' => 500,
					],
					'em' => [
						'max' => 50,
					],
					'rem' => [
						'max' => 50,
					],
				],
				'condition' => [
					"$shape_divider_base_control_key!" => '',
				],
				'selectors' => [
					"{{WRAPPER}} .e-link-in-bio__identity-image .elementor-shape-$shape_divider_side svg" => 'height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			$shape_divider_base_control_key . '_negative',
			[
				'label' => esc_html__( 'Invertir', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					"$shape_divider_base_control_key" => array_keys( Shapes::filter_shapes( 'has_negative' ) ),
				],
			]
		);

		$this->end_controls_section();
	}

	protected function p7_style_bio_controls(): void {
		parent::add_style_bio_controls();

		$this->start_injection(
			[
				'type' => 'control',
				'at' => 'before',
				'of' => 'bio_description_heading',
			]
		);

		$this->add_control(
			'bio_about_heading',
			[
				'label' => esc_html__( 'Encabezado de «Acerca de»', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'bio_about_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .e-link-in-bio' => '--e-link-in-bio-about-heading-color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'bio_about_typography',
				'selector' => '{{WRAPPER}} .e-link-in-bio__about-heading',
			]
		);

		$this->end_injection();
	}

	protected function p7_style_icons_controls(): void {
		parent::add_style_icons_controls();

		$this->start_injection(
			[
				'type' => 'control',
				'at' => 'after',
				'of' => 'icons_color',
			]
		);

		$this->add_control(
			'icons_color_background',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .e-link-in-bio' => '--e-link-in-bio-icon-background-color: {{VALUE}}',
				],
			]
		);

		$this->add_borders_control(
			'icons_border',
			[
				'selectors' => [],
			],
			[
				'selectors' => [
					'{{WRAPPER}} .e-link-in-bio' => '--e-link-in-bio-icon-border-width: {{SIZE}}{{UNIT}}',
				],
			],
			[
				'selectors' => [
					'{{WRAPPER}} .e-link-in-bio' => '--e-link-in-bio-icon-border-color: {{VALUE}}',
				],
			]
		);

		$this->end_injection();

		$this->start_injection(
			[
				'type' => 'control',
				'at' => 'after',
				'of' => 'icons_size',
			]
		);

		$this->add_control(
			'icons_color_text',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .e-link-in-bio' => '--e-link-in-bio-icon-text-color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'icons_typography',
				'selector' => '{{WRAPPER}} .e-link-in-bio__icon-label',
			]
		);

		$this->end_injection();
	}

	public static function p7_default_icons(): array {
		return [
			[
				'icon_platform' => Social_Network_Provider::TELEPHONE,
				'icon_text' => __( 'Llamar', 'pro-elements' ),
			],
			[
				'icon_platform' => Social_Network_Provider::EMAIL,
				'icon_text' => __( 'Correo', 'pro-elements' ),
			],
			[
				'icon_platform' => Social_Network_Provider::WHATSAPP,
				'icon_text' => 'WhatsApp',
			],
			[
				'icon_platform' => Social_Network_Provider::INSTAGRAM,
				'icon_text' => 'Instagram',
			],
			[
				'icon_platform' => Social_Network_Provider::LINKEDIN,
				'icon_text' => 'LinkedIn',
			],
			[
				'icon_platform' => Social_Network_Provider::BEHANCE,
				'icon_text' => 'Behance',
			],
		];
	}
}
